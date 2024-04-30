<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->morphs('authenticatable');
            $table->integer('group_id')->nullable();
            $table->string('name')->nullable();
            $table->string('token', 64)->unique();
            $table->text('abilities');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('auth_tokens');
    }
};
