<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_message_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('response_type')->index();
            $table->longText('response_text');
            $table->json('parser_result_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_responses');
    }
};
