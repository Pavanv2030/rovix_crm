<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueIndexToTemplates extends Migration
{
    /**
     * TemplatesController::store() had no duplicate-name check, so two
     * templates named "hello_world" (same account, same language) both
     * existed — which then broke the automation builder's template
     * dropdown (Alpine's :key collision on duplicate names). Meta itself
     * enforces unique template names per WABA; this constraint mirrors
     * that at the DB level as a backstop.
     */
    public function up()
    {
        $this->db->query(
            'ALTER TABLE message_templates ADD UNIQUE KEY templates_account_name_lang_unique (account_id, name, language)'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE message_templates DROP INDEX templates_account_name_lang_unique');
    }
}
