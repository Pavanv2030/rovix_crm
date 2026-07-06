<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\ContactModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\SessionWindow;

class CatalogController extends BaseController
{
    public function fetchCatalogs()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['phone_number_id'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Phone number ID not configured in WhatsApp settings']);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogs($waConfig['phone_number_id'], $accessToken);
            return $this->response->setJSON(['catalogs' => $result['data'] ?? []]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    public function products()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['catalog_id'])) {
            return $this->response->setJSON(['products' => [], 'catalog_connected' => false]);
        }

        // Serve from DB cache (populated by Sync Now)
        $products = [];
        if (!empty($waConfig['catalog_products'])) {
            $products = json_decode($waConfig['catalog_products'], true) ?? [];
        }

        return $this->response->setJSON([
            'products'          => $products,
            'catalog_id'        => $waConfig['catalog_id'],
            'catalog_connected' => true,
        ]);
    }

    public function sendCatalog()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $bodyText       = $this->request->getPost('body_text') ?: 'Browse our products';
        $footerText     = $this->request->getPost('footer_text');

        if (empty($conversationId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'conversation_id required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation) return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        if (!$waConfig)     return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        if (!$contact)      return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact not found']);

        if (empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'No catalog connected. Go to Settings → Catalog.']);
        }

        if (!SessionWindow::isOpen($conversation['last_customer_message_at'] ?? null)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => "Customer's 24-hour session window has closed — catalog messages can't be delivered outside it. Send an Approved Template instead.",
            ]);
        }

        $metaApi     = new MetaApi();
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        // Thumbnail retailer_id from the freshest cached product (populated
        // by Sync Now). If Meta hasn't finished indexing it into the
        // WhatsApp-side catalog graph yet, the send below auto-retries
        // without a thumbnail rather than failing outright.
        $thumbnailRetailerId = null;
        if (!empty($waConfig['catalog_products'])) {
            $cachedProducts      = json_decode($waConfig['catalog_products'], true) ?? [];
            $thumbnailRetailerId = $cachedProducts[0]['retailer_id'] ?? null;
        }

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'catalog',
            'content_text'    => $bodyText,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            try {
                $response = $metaApi->sendCatalogMessage(
                    $waConfig['phone_number_id'],
                    $accessToken,
                    $contact['phone_normalized'],
                    $bodyText,
                    $footerText,
                    $thumbnailRetailerId
                );
            } catch (\Exception $e) {
                // Thumbnail product not yet indexed on WhatsApp's catalog
                // graph (Meta 131009) — retry once with no thumbnail so the
                // send still succeeds and just shows the full catalog.
                if ($thumbnailRetailerId && str_contains($e->getMessage(), '131009')) {
                    $response = $metaApi->sendCatalogMessage(
                        $waConfig['phone_number_id'],
                        $accessToken,
                        $contact['phone_normalized'],
                        $bodyText,
                        $footerText,
                        null
                    );
                } else {
                    throw $e;
                }
            }

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '[Catalog]',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    public function sendProduct()
    {
        $conversationId    = $this->request->getPost('conversation_id');
        $productRetailerId = $this->request->getPost('product_retailer_id');
        $bodyText          = $this->request->getPost('body_text') ?? '';

        if (!$conversationId || !$productRetailerId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'conversation_id and product_retailer_id required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation || !$waConfig || !$contact) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Resource not found']);
        }

        if (empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No catalog connected']);
        }

        if (!SessionWindow::isOpen($conversation['last_customer_message_at'] ?? null)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => "Customer's 24-hour session window has closed — product messages can't be delivered outside it. Send an Approved Template instead.",
            ]);
        }

        $accessToken  = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'product',
            'content_text'    => 'Product: ' . $productRetailerId,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendSingleProduct(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $waConfig['catalog_id'],
                $productRetailerId,
                $bodyText
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '[Product]',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    public function sendMultiProduct()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $headerText     = $this->request->getPost('header_text') ?? 'Our Products';
        $bodyText       = $this->request->getPost('body_text') ?? 'Browse and order below';
        $footerText     = $this->request->getPost('footer_text');
        $sections       = json_decode($this->request->getPost('sections') ?? '[]', true);

        if (!$conversationId || empty($sections)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'conversation_id and sections required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation || !$waConfig || !$contact || empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Missing required data']);
        }

        if (!SessionWindow::isOpen($conversation['last_customer_message_at'] ?? null)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => "Customer's 24-hour session window has closed — product list messages can't be delivered outside it. Send an Approved Template instead.",
            ]);
        }

        // Meta hard limits: max 10 sections per message, max 30 products
        // total across all sections, max 30 products within any one section.
        if (count($sections) > 10) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Maximum 10 sections allowed per message']);
        }
        $totalProducts = 0;
        foreach ($sections as $section) {
            $count = count($section['product_items'] ?? []);
            if ($count > 30) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'Maximum 30 products allowed per section']);
            }
            $totalProducts += $count;
        }
        if ($totalProducts > 30) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Maximum 30 products allowed per message across all sections']);
        }

        $accessToken  = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'product_list',
            'content_text'    => $headerText,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendMultiProduct(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $waConfig['catalog_id'],
                $headerText,
                $bodyText,
                $sections,
                $footerText
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '[Product List]',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    private function resolveConversationContext(string $conversationId): array
    {
        $conversation = (new ConversationModel())->find($conversationId);
        $waConfig     = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();
        $contact      = $conversation
            ? (new ContactModel())->find($conversation['contact_id'])
            : null;

        return [$conversation, $waConfig, $contact];
    }
}
