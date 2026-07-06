<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WidenReplyToMessageId extends Migration
{
    /**
     * reply_to_message_id was CHAR(36) — sized like a UUID primary key, but
     * it actually stores a WhatsApp message ID (wamid.*), which routinely
     * runs 80+ characters. Every reply got silently truncated on insert and
     * could never match whatsapp_message_id (VARCHAR(255)) on lookup, so
     * quoted-reply linking has never worked.
     */
    public function up()
    {
        $this->forge->modifyColumn('messages', [
            'reply_to_message_id' => [
                'name'       => 'reply_to_message_id',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('messages', [
            'reply_to_message_id' => [
                'name'       => 'reply_to_message_id',
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
            ],
        ]);
    }
}
