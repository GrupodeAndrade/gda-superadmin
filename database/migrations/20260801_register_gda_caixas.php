<?php
declare(strict_types=1);

// Entrada explícita da migração; o serviço garante reexecução segura.
App\Services\ModuleMigrationService::run();
