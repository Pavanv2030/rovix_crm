<?php

namespace App\Controllers;

use App\Models\WhatsAppConfigModel;
use App\Models\CatalogOrderModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class CatalogController extends BaseController
{
    public function index()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        $products      = [];
        $productsError = null;
        if ($waConfig && !empty($waConfig['catalog_id'])) {
            // Use cached products from last sync — no live Meta API call
            if (!empty($waConfig['catalog_products'])) {
                $products = json_decode($waConfig['catalog_products'], true) ?? [];
            } else {
                // No cache yet — try live fetch once
                try {
                    $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
                    $result      = (new MetaApi())->getCatalogProducts($waConfig['catalog_id'], $accessToken);
                    $products    = $result['data'] ?? [];
                } catch (\Exception $e) {
                    $productsError = $e->getMessage();
                    log_message('warning', 'Catalog product fetch failed: ' . $e->getMessage());
                }
            }
        }

        return view('catalog/index', [
            'pageTitle'     => 'Catalog',
            'waConfig'      => $waConfig,
            'products'      => $products,
            'productsError' => $productsError,
        ]);
    }

    public function connect()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $catalogId = trim($this->request->getPost('catalog_id') ?? '');
        if (empty($catalogId)) {
            return $this->response->setJSON(['error' => 'catalog_id required'])->setStatusCode(400);
        }

        $waConfigModel = new WhatsAppConfigModel();
        $waConfig      = $waConfigModel->where('account_id', session('account_id'))->first();

        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not connected'])->setStatusCode(400);
        }

        $waConfigModel->update($waConfig['id'], [
            'catalog_id'        => $catalogId,
            'catalog_synced_at' => date('Y-m-d H:i:s'),
        ]);

        // WhatsApp Manager's catalog-connect toggle does not reliably create
        // the phone number's whatsapp_commerce_settings object, which causes
        // catalog_message sends to fail with 131009 even though the catalog
        // is genuinely attached. Explicitly enable it here every time.
        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            (new MetaApi())->enableCommerceSettings($waConfig['phone_number_id'], $accessToken);
            log_message('info', 'Commerce settings enabled for phone_number_id: ' . $waConfig['phone_number_id']);
        } catch (\Exception $e) {
            log_message('error', 'enableCommerceSettings failed during catalog connect: ' . $e->getMessage());
            // Continue anyway - catalog connect should work even if this fails
        }

        return $this->response->setJSON(['success' => true, 'product_count' => 0]);
    }

    public function disconnect()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $waConfigModel = new WhatsAppConfigModel();
        $waConfig      = $waConfigModel->where('account_id', session('account_id'))->first();

        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not connected'])->setStatusCode(400);
        }

        $waConfigModel->update($waConfig['id'], [
            'catalog_id'        => null,
            'catalog_name'      => null,
            'catalog_synced_at' => null,
            'catalog_products'  => null,
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function sync()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['catalog_id'])) {
            return $this->response->setJSON(['error' => 'No catalog connected'])->setStatusCode(400);
        }

        $products = [];
        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogProducts($waConfig['catalog_id'], $accessToken);
            $products    = $result['data'] ?? [];
        } catch (\Exception $e) {
            log_message('warning', 'Catalog sync products failed: ' . $e->getMessage());
        }

        (new WhatsAppConfigModel())->update($waConfig['id'], [
            'catalog_synced_at' => date('Y-m-d H:i:s'),
            'catalog_products'  => $products ? json_encode($products) : null,
        ]);

        return $this->response->setJSON(['success' => true, 'product_count' => count($products)]);
    }

    public function orders()
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('catalog_orders co')
            ->select('co.*, c.name as contact_name, c.phone')
            ->join('contacts c', 'c.id = co.contact_id', 'left')
            ->where('co.account_id', session('account_id'));

        $catalogFilter = $this->request->getGet('catalog_id');
        $statusFilter  = $this->request->getGet('status');
        $search        = $this->request->getGet('q');

        if ($catalogFilter) $builder->where('co.catalog_id', $catalogFilter);
        if ($statusFilter)  $builder->where('co.status', $statusFilter);
        if ($search) {
            $builder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.phone', $search)
            ->groupEnd();
        }

        $orders = $builder->orderBy('co.created_at', 'DESC')->get()->getResultArray();

        foreach ($orders as &$order) {
            $order['order_items'] = json_decode($order['order_items'] ?? '[]', true) ?? [];
        }

        $catalogs = $db->table('catalog_orders')
            ->select('catalog_id')
            ->where('account_id', session('account_id'))
            ->where('catalog_id IS NOT NULL')
            ->distinct()
            ->get()->getResultArray();

        return view('catalog/orders', [
            'pageTitle'     => 'Catalog Orders',
            'orders'        => $orders,
            'catalogs'      => $catalogs,
            'statusOptions' => ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
            'currentFilters' => [
                'catalog_id' => $catalogFilter,
                'status'     => $statusFilter,
                'q'          => $search,
            ],
        ]);
    }

    public function updateOrderStatus(string $orderId)
    {
        $status  = $this->request->getPost('status');
        $allowed = ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $allowed)) {
            return $this->response->setJSON(['error' => 'Invalid status'])->setStatusCode(400);
        }

        $orderModel = new CatalogOrderModel();
        $order      = $orderModel->where('account_id', session('account_id'))->find($orderId);

        if (!$order) {
            return $this->response->setJSON(['error' => 'Order not found'])->setStatusCode(404);
        }

        $orderModel->update($orderId, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true]);
    }
}
