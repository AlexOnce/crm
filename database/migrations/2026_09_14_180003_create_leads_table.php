<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->string('phone', 30)->index();
            $table->string('type', 50)->default('compra_pasaje')->index();

            // Datos del pasajero y vuelo
            $table->string('passenger_name')->nullable();
            $table->string('passport_number', 50)->nullable()->index();
            $table->string('origin', 10)->nullable();
            $table->string('destination', 10)->nullable();
            $table->date('flight_date')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();

            // Estado y Emisión
            $table->enum('status', ['nuevo', 'contactado', 'en_emision', 'emitido', 'cancelado'])
                ->default('nuevo')
                ->index();
            $table->string('booking_reference', 30)->nullable()->unique();
            $table->string('ticket_pdf_path')->nullable();
            $table->text('details')->nullable();
            $table->json('bot_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
