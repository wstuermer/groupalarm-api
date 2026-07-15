<?php foreach (flash_take() as $flash): ?>
    <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endforeach; ?>
