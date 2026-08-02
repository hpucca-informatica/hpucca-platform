<?php

declare(strict_types=1);
?>
<?php if ($flash !== []): ?>
    <section class="flash-list" aria-label="Mensagens">
        <?php foreach ($flash as $message): ?>
            <?php if (is_string($message)): ?>
                <p class="flash-message"><?= $e($message) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
