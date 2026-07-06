<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Auth routes
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->get('signup', 'AuthController::signup');
$routes->post('signup', 'AuthController::register');
$routes->get('forgot-password', 'AuthController::forgotPassword');
$routes->post('logout', 'AuthController::logout');

// Dashboard
$routes->get('/', 'DashboardController::index');
$routes->get('dashboard', 'DashboardController::index');

// Contacts
$routes->get('contacts', 'ContactsController::index');
$routes->get('contacts/create', 'ContactsController::create');
$routes->post('contacts', 'ContactsController::store');
$routes->get('contacts/import', 'ContactsController::import');
$routes->post('contacts/import/process', 'ContactsController::processImport');
$routes->post('contacts/import/confirm', 'ContactsController::confirmImport');
$routes->get('contacts/(:segment)', 'ContactsController::view/$1');
$routes->get('contacts/(:segment)/edit', 'ContactsController::edit/$1');
$routes->post('contacts/(:segment)/update', 'ContactsController::update/$1');
$routes->post('contacts/(:segment)/delete', 'ContactsController::delete/$1');

// Settings
$routes->get('settings',                          'SettingsController::index');
$routes->post('settings/update-account',          'SettingsController::updateAccount');
$routes->get('settings/whatsapp',                 'SettingsController::whatsapp');
$routes->post('settings/update-whatsapp',         'SettingsController::updateWhatsApp');
$routes->post('settings/test-whatsapp',           'SettingsController::testWhatsApp');
$routes->post('settings/fetch-number-info',       'SettingsController::fetchNumberInfo');
$routes->get('settings/ai',                       'SettingsController::ai');
$routes->post('settings/update-ai',               'SettingsController::updateAi');
$routes->get('settings/notifications',            'SettingsController::notifications');
$routes->post('settings/update-notifications',    'SettingsController::updateNotifications');
$routes->get('settings/api-keys',                 'SettingsController::apiKeys');
$routes->post('settings/regenerate-api-key',      'SettingsController::regenerateApiKey');
$routes->get('settings/webhooks',                 'SettingsController::webhooks');
$routes->get('settings/tags',                     'SettingsController::tags');
$routes->get('settings/lead-statuses',            'SettingsController::leadStatuses');
$routes->get('settings/custom-fields',            'SettingsController::customFields');

// Tags API
$routes->get('api/tags', 'Api\TagsController::index');
$routes->post('api/tags', 'Api\TagsController::store');
$routes->post('api/tags/(:segment)', 'Api\TagsController::update/$1');
$routes->delete('api/tags/(:segment)', 'Api\TagsController::delete/$1');

// Custom Fields API
$routes->get('api/custom-fields', 'Api\CustomFieldsController::index');
$routes->post('api/custom-fields', 'Api\CustomFieldsController::store');
$routes->post('api/custom-fields/(:segment)', 'Api\CustomFieldsController::update/$1');
$routes->delete('api/custom-fields/(:segment)', 'Api\CustomFieldsController::delete/$1');

// Contact Note API
$routes->post('api/contacts/note', 'Api\ConversationController::addContactNote');

// Templates
$routes->get('templates', 'TemplatesController::index');
$routes->get('templates/create', 'TemplatesController::create');
$routes->post('templates', 'TemplatesController::store');
$routes->post('templates/fetch-from-meta', 'TemplatesController::fetchFromMeta');
$routes->post('templates/upload-media', 'TemplatesController::uploadMedia');
$routes->get('templates/(:segment)/edit', 'TemplatesController::edit/$1');
$routes->get('templates/(:segment)/summary', 'TemplatesController::summary/$1');
$routes->post('templates/(:segment)/submit', 'TemplatesController::submitForApproval/$1');
$routes->post('templates/(:segment)/refresh', 'TemplatesController::refreshStatus/$1');
$routes->post('templates/(:segment)/delete', 'TemplatesController::delete/$1');
$routes->get('templates/(:segment)', 'TemplatesController::view/$1');
$routes->post('templates/(:segment)', 'TemplatesController::update/$1');

// Broadcasts
$routes->get('broadcasts', 'BroadcastsController::index');
$routes->get('broadcasts/create', 'BroadcastsController::create');
$routes->post('broadcasts', 'BroadcastsController::store');
$routes->get('broadcasts/(:segment)', 'BroadcastsController::view/$1');
$routes->get('broadcasts/(:segment)/edit', 'BroadcastsController::edit/$1');
$routes->post('broadcasts/(:segment)/update', 'BroadcastsController::update/$1');
$routes->post('broadcasts/(:segment)/send', 'BroadcastsController::sendNow/$1');
$routes->post('broadcasts/(:segment)/schedule', 'BroadcastsController::schedule/$1');
$routes->post('broadcasts/(:segment)/cancel', 'BroadcastsController::cancel/$1');
$routes->post('broadcasts/(:segment)/delete', 'BroadcastsController::delete/$1');
$routes->get('broadcasts/(:segment)/export', 'BroadcastsController::export/$1');
$routes->post('broadcasts/(:segment)/retry', 'BroadcastsController::retryFailed/$1');
$routes->post('broadcasts/(:segment)/duplicate', 'BroadcastsController::duplicate/$1');

// Flows
$routes->get('flows', 'FlowsController::index');
$routes->get('flows/create', 'FlowsController::create');
$routes->post('flows', 'FlowsController::store');
$routes->get('flows/(:segment)/edit', 'FlowsController::edit/$1');
$routes->get('flows/(:segment)/test', 'FlowsController::test/$1');
$routes->post('flows/(:segment)/test-message', 'FlowsController::testMessage/$1');
$routes->post('flows/(:segment)/test-reset', 'FlowsController::testReset/$1');
$routes->post('flows/(:segment)/toggle', 'FlowsController::toggle/$1');
$routes->post('flows/(:segment)/delete', 'FlowsController::delete/$1');
$routes->post('flows/(:segment)/duplicate', 'FlowsController::duplicate/$1');
$routes->get('flows/(:segment)', 'FlowsController::view/$1');
$routes->post('flows/(:segment)', 'FlowsController::update/$1');

// Flow Node Schemas API
$routes->get('api/flows/node-types', 'Api\FlowNodesController::getNodeTypes');
$routes->get('api/flows/node-types/(:segment)', 'Api\FlowNodesController::getNodeType/$1');

// Broadcasts API
$routes->post('api/broadcasts/count-recipients', 'Api\BroadcastsController::countRecipients');
$routes->post('api/broadcasts/quick-send', 'Api\BroadcastsController::quickSend');
$routes->get('api/broadcasts/(:segment)/progress', 'Api\BroadcastsController::getProgress/$1');

// Automations
$routes->get('automations', 'AutomationsController::index');
$routes->get('automations/create', 'AutomationsController::create');
$routes->post('automations', 'AutomationsController::store');
$routes->get('automations/(:segment)', 'AutomationsController::view/$1');
$routes->get('automations/(:segment)/edit', 'AutomationsController::edit/$1');
$routes->post('automations/(:segment)/update', 'AutomationsController::update/$1');
$routes->post('automations/(:segment)/toggle', 'AutomationsController::toggle/$1');
$routes->post('automations/(:segment)/delete', 'AutomationsController::delete/$1');

// Pipelines
$routes->get('pipelines', 'PipelinesController::index');
$routes->get('pipelines/create', 'PipelinesController::create');
$routes->post('pipelines', 'PipelinesController::store');
$routes->get('pipelines/(:segment)/board', 'PipelinesController::board/$1');
$routes->get('pipelines/(:segment)/edit', 'PipelinesController::edit/$1');
$routes->post('pipelines/(:segment)', 'PipelinesController::update/$1');
$routes->post('pipelines/(:segment)/delete', 'PipelinesController::delete/$1');

// Deals
$routes->get('deals/create', 'DealsController::create');
$routes->post('deals', 'DealsController::store');
$routes->get('deals/(:segment)', 'DealsController::view/$1');
$routes->get('deals/(:segment)/edit', 'DealsController::edit/$1');
$routes->post('deals/(:segment)/update', 'DealsController::update/$1');
$routes->post('deals/(:segment)/status', 'DealsController::updateStatus/$1');
$routes->post('deals/(:segment)/delete', 'DealsController::delete/$1');

// Pipeline Stages API
$routes->post('api/pipelines/(:segment)/stages', 'Api\PipelineStagesController::store/$1');
$routes->post('api/stages/(:segment)', 'Api\PipelineStagesController::update/$1');
$routes->post('api/stages/reorder', 'Api\PipelineStagesController::reorder');
$routes->delete('api/stages/(:segment)', 'Api\PipelineStagesController::delete/$1');

// Deals API
$routes->post('api/deals/(:segment)/move', 'Api\DealsController::move/$1');
$routes->post('api/deals/(:segment)/assign', 'Api\DealsController::assign/$1');
$routes->post('api/deals/(:segment)/value', 'Api\DealsController::updateValue/$1');
$routes->post('api/deals/(:segment)/whatsapp', 'Api\DealsController::sendWhatsApp/$1');
$routes->post('api/deals/(:segment)/generate-message', 'Api\DealsController::generateMessage/$1');
$routes->get('api/pipelines/(:segment)/stages', 'Api\DealsController::stages/$1');

// Reports
$routes->get('reports', 'ReportsController::sendingHistory');
$routes->get('reports/sending-history', 'ReportsController::sendingHistory');
$routes->get('reports/scheduled-log', 'ReportsController::scheduledLog');

// Catalog
$routes->get('catalog',                           'CatalogController::index');
$routes->post('catalog/connect',                  'CatalogController::connect');
$routes->post('catalog/disconnect',               'CatalogController::disconnect');
$routes->post('catalog/sync',                     'CatalogController::sync');
$routes->get('catalog/orders',                    'CatalogController::orders');
$routes->post('catalog/orders/(:segment)/status', 'CatalogController::updateOrderStatus/$1');

// Catalog API
$routes->get('api/catalog/fetch-catalogs',        'Api\CatalogController::fetchCatalogs');
$routes->get('api/catalog/products',              'Api\CatalogController::products');
$routes->post('api/catalog/send-catalog',         'Api\CatalogController::sendCatalog');
$routes->post('api/catalog/send-product',         'Api\CatalogController::sendProduct');
$routes->post('api/catalog/send-multi-product',   'Api\CatalogController::sendMultiProduct');

// Inbox
$routes->get('inbox', 'InboxController::index');
$routes->get('inbox/conversation/(:segment)', 'InboxController::conversation/$1');
$routes->get('inbox/search', 'InboxController::search');

// Inbox auto-refresh polling API
$routes->get('api/inbox/conversations', 'Api\InboxController::conversations');
$routes->get('api/inbox/messages/(:segment)', 'Api\InboxController::messages/$1');

// Inbox AI translate/rewrite assist
$routes->post('api/ai/translate-outgoing', 'Api\AiAssistController::translateOutgoing');
$routes->post('api/ai/rewrite',            'Api\AiAssistController::rewrite');
$routes->post('api/ai/translate-incoming', 'Api\AiAssistController::translateIncoming');

// Conversation actions API
$routes->post('api/conversations/assign', 'Api\ConversationController::assign');
$routes->post('api/conversations/status', 'Api\ConversationController::updateStatus');
$routes->post('api/conversations/lead-status', 'Api\ConversationController::updateLeadStatus');
$routes->post('api/conversations/tag', 'Api\ConversationController::addTag');
$routes->post('api/conversations/note', 'Api\ConversationController::addNote');

// Lead statuses (Settings)
$routes->get('api/lead-statuses', 'Api\ConversationStatusesController::index');
$routes->post('api/lead-statuses', 'Api\ConversationStatusesController::store');
$routes->post('api/lead-statuses/(:segment)', 'Api\ConversationStatusesController::update/$1');
$routes->delete('api/lead-statuses/(:segment)', 'Api\ConversationStatusesController::delete/$1');

// Media API
$routes->post('api/media/upload', 'Api\MediaController::upload');
$routes->get('api/media/download/(:segment)', 'Api\MediaController::download/$1');

// Team Management
$routes->get('team', 'TeamController::index');
$routes->post('team/invite', 'TeamController::invite');
$routes->get('team/activity-log', 'TeamController::activityLog');
$routes->get('team/accept/(:any)', 'TeamController::accept/$1');
$routes->post('team/accept/process', 'TeamController::processAccept');
$routes->post('team/invitations/(:segment)/resend', 'TeamController::resendInvitation/$1');
$routes->post('team/invitations/(:segment)/cancel', 'TeamController::cancelInvitation/$1');
$routes->post('team/(:segment)/update-role', 'TeamController::updateRole/$1');
$routes->post('team/(:segment)/toggle-active', 'TeamController::toggleActive/$1');
$routes->post('team/(:segment)/remove', 'TeamController::remove/$1');

// OTP Verification
$routes->post('api/otp/send', 'Api\OtpController::send');
$routes->post('api/otp/verify', 'Api\OtpController::verify');

// Appointments (web)
$routes->get('appointments',                                'AppointmentsController::index');
$routes->get('appointments/types',                          'AppointmentsController::types');
$routes->post('appointments/types/create',                  'AppointmentsController::createType');
$routes->post('appointments/types/(:segment)/delete',       'AppointmentsController::deleteType/$1');
$routes->post('appointments/flows/create',                  'AppointmentsController::createFlow');
$routes->post('appointments/(:segment)/cancel',             'AppointmentsController::cancel/$1');
$routes->post('appointments/(:segment)/reschedule',         'AppointmentsController::reschedule/$1');
$routes->post('appointments/(:segment)/status',             'AppointmentsController::updateStatus/$1');
$routes->post('appointments/(:segment)/send-reminder',      'AppointmentsController::sendReminder/$1');
$routes->get('appointments/google/connect',                 'AppointmentsController::googleConnect');
$routes->get('appointments/google/callback',                'AppointmentsController::googleCallback');
$routes->post('appointments/google/disconnect',             'AppointmentsController::googleDisconnect');

// Appointments API
$routes->get('api/appointments/types',                      'Api\AppointmentsController::types');
$routes->get('api/appointments/slots',                      'Api\AppointmentsController::slots');
$routes->post('api/appointments/send-flow',                 'Api\AppointmentsController::sendFlow');

// Flow Data Exchange (Meta calls this — no auth, see Filters.php 'except' rules)
$routes->post('api/flows/data-exchange',                    'Api\FlowDataController::handle');

// Public booking page (no auth, see Filters.php 'except' rules)
$routes->get('booking/test-flows',                          'Home::index');
$routes->get('booking/(:segment)',                          'BookingController::show/$1');
$routes->post('booking/(:segment)/reschedule',              'BookingController::reschedule/$1');

// WhatsApp webhook (no auth)
$routes->get('api/whatsapp/webhook', 'Api\WebhookController::verify');
$routes->post('api/whatsapp/webhook', 'Api\WebhookController::handle', ['filter' => 'webhook_signature']);

// WhatsApp send/react (auth required)
$routes->post('api/whatsapp/send', 'Api\SendController::send');
$routes->post('api/whatsapp/send-template', 'Api\SendController::sendTemplate');
$routes->post('api/whatsapp/react', 'Api\ReactController::react');
