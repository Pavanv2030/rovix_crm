<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'auth'               => \App\Filters\AuthFilter::class,
        'account'            => \App\Filters\AccountFilter::class,
        'role'               => \App\Filters\RoleFilter::class,
        'write_role'         => \App\Filters\WriteRoleFilter::class,
        'api_key'            => \App\Filters\ApiKeyFilter::class,
        'webhook_signature'  => \App\Filters\WebhookSignatureFilter::class,
        'rate_limit'         => \App\Filters\RateLimitFilter::class,
    ];

    public array $required = [
        'before' => [
            'forcehttps',
        ],
        'after' => [
            'performance',
        ],
    ];

    public array $globals = [
        'before' => [
            'api_key' => ['except' => ['webhook/*', 'api/whatsapp/webhook', 'api/flows/data-exchange', 'booking/*', 'media/template/*', 'demo/send-report', 'login', 'signup', 'forgot-password', 'reset-password/*', 'team/accept/*']],
            'csrf'    => ['except' => ['webhook/*', 'api/whatsapp/webhook', 'api/flows/data-exchange', 'booking/*', 'demo/send-report']],
            'auth'    => ['except' => ['webhook/*', 'api/whatsapp/webhook', 'api/flows/data-exchange', 'booking/*', 'media/template/*', 'demo/send-report']],
            'account' => ['except' => ['webhook/*', 'api/whatsapp/webhook', 'api/flows/data-exchange', 'booking/*', 'media/template/*', 'demo/send-report']],
        ],
        'after' => [
            'secureheaders',
        ],
    ];

    public array $methods = [];

    public array $filters = [
        'rate_limit' => [
            'before' => [
                'login',
                'signup',
                'api/otp/send',
                'api/otp/verify',
                'team/accept/process',
                'forgot-password',
                'booking/*/reschedule',
            ],
        ],
        'role:agent' => [
            'before' => [
                'api/whatsapp/send',
                'api/whatsapp/send-template',
                'api/whatsapp/react',
                'api/deals/*/move',
                'api/deals/*/assign',
                'api/deals/*/value',
                'api/deals/*/whatsapp',
                'api/deals/*/generate-message',
                'api/conversations/assign',
                'api/conversations/status',
                'api/conversations/lead-status',
                'api/conversations/tag',
                'api/conversations/note',
                'api/contacts/note',
                'api/media/upload',
                'api/ai/translate-outgoing',
                'api/ai/rewrite',
                'api/ai/translate-incoming',
                'api/tags',
                'api/tags/*',
                'api/custom-fields',
                'api/custom-fields/*',
                'api/lead-statuses',
                'api/lead-statuses/*',
                'api/pipelines/*/stages',
                'api/stages/*',
                'api/stages/reorder',
                'api/catalog/send-catalog',
                'api/catalog/send-product',
                'api/catalog/send-multi-product',
                'api/appointments/send-flow',
                'api/broadcasts/quick-send',
                'api/broadcasts/count-recipients',
            ],
        ],
        'write_role:agent' => [
            'before' => [
                'broadcasts',
                'broadcasts/*',
                'contacts',
                'contacts/*',
                'templates',
                'templates/*',
                'flows',
                'flows/*',
                'automations',
                'automations/*',
                'pipelines',
                'pipelines/*',
                'deals',
                'deals/*',
                'catalog',
                'catalog/*',
                'appointments',
                'appointments/*',
                'settings',
                'settings/*',
            ],
        ],
        'write_role:admin' => [
            'before' => [
                'team',
                'team/*',
            ],
        ],
    ];
}