<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanChatMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:clean-messages {--force : Forzar la limpieza sin solicitar confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia la base de datos de mensajes, conversaciones, leads y adjuntos para puesta en producción.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Estás seguro de que deseas purgar TODOS los mensajes, conversaciones, leads y adjuntos?')) {
            $this->warn('Operación cancelada por el usuario.');

            return 0;
        }

        $this->info('Limpiando tablas de atención y mensajes para pase a producción...');

        Schema::disableForeignKeyConstraints();

        $messagesCount = DB::table('chat_messages')->count();
        $attachmentsCount = DB::table('attachments')->count();
        $leadsCount = DB::table('leads')->count();
        $conversationsCount = DB::table('conversations')->count();

        DB::table('chat_messages')->truncate();
        DB::table('attachments')->truncate();
        DB::table('leads')->truncate();
        DB::table('conversations')->truncate();

        Schema::enableForeignKeyConstraints();

        // Limpiar caché de conversaciones activas
        Cache::flush();

        $this->table(
            ['Tabla Purgada', 'Registros Eliminados', 'Estado'],
            [
                ['chat_messages', $messagesCount, 'Limpia (0 registros)'],
                ['attachments', $attachmentsCount, 'Limpia (0 registros)'],
                ['leads', $leadsCount, 'Limpia (0 registros)'],
                ['conversations', $conversationsCount, 'Limpia (0 registros)'],
            ]
        );

        $this->info('✅ Tablas de mensajes y conversaciones limpiadas exitosamente.');
        $this->info('🛡️ Usuarios (users), Plantillas de Bot (bot_messages) y Configuraciones (settings) se mantienen intactos.');

        return 0;
    }
}
