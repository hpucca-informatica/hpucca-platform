<?php

declare(strict_types=1);
?>
<?php if ($flash !== []): ?>
    <section class="flash-list" aria-label="Mensagens" aria-live="polite">
        <?php foreach ($flash as $flashMessage): ?>
            <?php
            $type = 'info';
            $message = '';

            if (is_string($flashMessage)) {
                $message = $flashMessage;
            } elseif (is_array($flashMessage) && is_string($flashMessage['message'] ?? null)) {
                $type = is_string($flashMessage['type'] ?? null) ? $flashMessage['type'] : 'info';
                $message = $flashMessage['message'];
            }

            $type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
            $role = in_array($type, ['error', 'warning'], true) ? 'alert' : 'status';
            $labels = ['success' => 'Sucesso', 'error' => 'Erro', 'warning' => 'Aviso', 'info' => 'Info'];
            ?>
            <?php if ($message !== ''): ?>
                <div class="flash-message flash-<?= $e($type) ?>" role="<?= $e($role) ?>">
                    <span class="flash-marker"><?= $e($labels[$type]) ?></span>
                    <p><?= $e($message) ?></p>
                    <button type="button" class="flash-close" data-dismiss-flash aria-label="Fechar mensagem">&times;</button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
