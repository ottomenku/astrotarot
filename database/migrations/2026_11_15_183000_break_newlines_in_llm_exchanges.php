<?php

use App\Services\LlmTranscript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('llm_exchanges')) {
            return;
        }

        DB::table('llm_exchanges')->orderBy('id')->select(['id', 'request_body', 'response_body'])->each(function (object $row): void {
            DB::table('llm_exchanges')->where('id', $row->id)->update([
                'request_body' => LlmTranscript::readable((string) $row->request_body),
                'response_body' => LlmTranscript::readable((string) $row->response_body),
            ]);
        });
    }

    public function down(): void
    {
    }
};
