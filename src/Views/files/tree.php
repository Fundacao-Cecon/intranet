<?php
/**
 * Um nível da árvore lateral (recursivo).
 *
 * @var array  $nodes    [['name','path','locked','hasChildren','children' => array|null], ...]
 * @var string $current  pasta aberta agora ('' = raiz)
 * @var int    $level    nível ARIA (a raiz "Início" é 1)
 */
?><ul role="group">
<?php foreach ($nodes as $n): ?>
    <?php $open = $n['children'] !== null; ?>
    <li role="treeitem" tabindex="-1" aria-level="<?= (int) $level ?>"<?= $n['hasChildren'] ? ' aria-expanded="' . ($open ? 'true' : 'false') . '"' : '' ?><?= $n['locked'] ? ' aria-disabled="true"' : '' ?> data-path="<?= h($n['path']) ?>" data-loaded="<?= $open || !$n['hasChildren'] ? '1' : '0' ?>">
        <div class="tree-row<?= $n['path'] === $current ? ' current' : '' ?>">
            <span class="tree-toggle" aria-hidden="true"><?= $n['hasChildren'] ? '<i class="fas fa-chevron-right"></i>' : '' ?></span>
            <?php if ($n['locked']): ?>
                <span class="tree-locked" title="Acesso restrito"><i aria-hidden="true" class="fas fa-lock"></i> <?= h($n['name']) ?> <span class="sr-only">(acesso restrito)</span></span>
            <?php else: ?>
                <a href="<?= h(url(['dir' => $n['path']])) ?>" tabindex="-1"<?= $n['path'] === $current ? ' aria-current="page"' : '' ?>><i aria-hidden="true" class="fas fa-folder"></i> <?= h($n['name']) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($open && $n['children']): ?>
            <?php view('files/tree', ['nodes' => $n['children'], 'current' => $current, 'level' => $level + 1]); ?>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
