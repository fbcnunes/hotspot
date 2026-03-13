<?php
$filters = $userFilters ?? ['matricula' => '', 'name' => '', 'cpf' => '', 'groupname' => ''];
$paginationData = $pagination ?? ['page' => 1, 'pages' => 0, 'total' => 0, 'per_page' => 50];
$currentPage = max(1, (int) ($paginationData['page'] ?? 1));
$totalPages = max(0, (int) ($paginationData['pages'] ?? 0));
$totalUsers = (int) ($paginationData['total'] ?? 0);
$queryBase = http_build_query(array_filter([
    'matricula' => $filters['matricula'] ?? '',
    'name' => $filters['name'] ?? '',
    'cpf' => $filters['cpf'] ?? '',
    'groupname' => $filters['groupname'] ?? '',
], static fn ($value) => $value !== ''));
$returnQuery = $_SERVER['QUERY_STRING'] ?? '';
?>

<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Gestão de Usuários</h1>
    <p class="text-slate-500">Pesquise usuários por matrícula, nome, CPF ou grupo.</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 bg-slate-50">
        <h2 class="font-bold text-slate-800">Filtros de Pesquisa</h2>
        <p class="text-sm text-slate-500 mt-1">A listagem só é carregada depois que pelo menos um filtro for informado.</p>
    </div>
    <form method="GET" action="/admin/users" class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        <div>
            <label for="filter-matricula" class="block text-sm font-semibold text-slate-700 mb-2">Matrícula</label>
            <input
                id="filter-matricula"
                type="text"
                name="matricula"
                value="<?= htmlspecialchars($filters['matricula'] ?? '') ?>"
                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white"
                placeholder="Digite a matrícula"
            >
        </div>
        <div>
            <label for="filter-name" class="block text-sm font-semibold text-slate-700 mb-2">Nome</label>
            <input
                id="filter-name"
                type="text"
                name="name"
                value="<?= htmlspecialchars($filters['name'] ?? '') ?>"
                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white"
                placeholder="Digite o nome"
            >
        </div>
        <div>
            <label for="filter-cpf" class="block text-sm font-semibold text-slate-700 mb-2">CPF</label>
            <input
                id="filter-cpf"
                type="text"
                name="cpf"
                value="<?= htmlspecialchars($filters['cpf'] ?? '') ?>"
                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white"
                placeholder="Digite o CPF"
            >
        </div>
        <div>
            <label for="filter-groupname" class="block text-sm font-semibold text-slate-700 mb-2">Grupo</label>
            <select
                id="filter-groupname"
                name="groupname"
                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white"
            >
                <option value="">Todos os grupos</option>
                <?php foreach ($profiles as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?>" <?= ($filters['groupname'] ?? '') === $p['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-3">
            <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md">
                Pesquisar
            </button>
            <a href="/admin/users" class="px-4 py-3 border border-slate-200 text-slate-600 font-semibold rounded-lg hover:bg-slate-50 text-center">
                Limpar
            </a>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center gap-4">
        <div>
            <h2 class="font-bold text-slate-800">Resultado da Pesquisa</h2>
            <p class="text-sm text-slate-500 mt-1">
                <?php if (!empty($hasActiveFilters)): ?>
                    <?= $totalUsers ?> usuário(s) encontrado(s)
                <?php else: ?>
                    Informe um filtro para consultar a base
                <?php endif; ?>
            </p>
        </div>
        <?php if (!empty($hasActiveFilters) && $totalPages > 0): ?>
            <span class="text-xs text-slate-400">Página <?= $currentPage ?> de <?= $totalPages ?></span>
        <?php endif; ?>
    </div>

    <?php if (empty($hasActiveFilters)): ?>
        <div class="px-6 py-12 text-center text-slate-500">
            Use pelo menos um filtro acima para carregar os usuários de forma mais rápida e segura.
        </div>
    <?php elseif (empty($users)): ?>
        <div class="px-6 py-12 text-center text-slate-500">
            Nenhum usuário encontrado com os filtros informados.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 text-sm uppercase">
                    <tr>
                        <th class="px-6 py-4">Nome / Usuário</th>
                        <th class="px-6 py-4">CPF / Matrícula</th>
                        <th class="px-6 py-4">Perfil Atual</th>
                        <th class="px-6 py-4">Último Login</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($u['username']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600">CPF: <?= $u['cpf'] ?: '---' ?></div>
                                <div class="text-sm text-slate-500">Mat: <?= $u['matricula'] ?: '---' ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold uppercase">
                                    <?= htmlspecialchars($u['groupname'] ?: 'padrao') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <?= htmlspecialchars((string) ($u['last_login'] ?? '---')) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    onclick='openGroupModal("<?= htmlspecialchars($u["username"], ENT_QUOTES) ?>", "<?= htmlspecialchars($u["groupname"] ?: "padrao", ENT_QUOTES) ?>")'
                                    class="bg-slate-100 hover:bg-blue-600 hover:text-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold transition"
                                >
                                    Alterar Grupo
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <p class="text-sm text-slate-500">
                    Exibindo página <?= $currentPage ?> de <?= $totalPages ?>.
                </p>
                <div class="flex gap-2">
                    <?php if ($currentPage > 1): ?>
                        <a href="/admin/users?<?= htmlspecialchars($queryBase . ($queryBase !== '' ? '&' : '') . 'page=' . ($currentPage - 1)) ?>" class="px-4 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="/admin/users?<?= htmlspecialchars($queryBase . ($queryBase !== '' ? '&' : '') . 'page=' . ($currentPage + 1)) ?>" class="px-4 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">
                            Próxima
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="modal-group" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Alterar Grupo</h2>
            <button onclick="document.getElementById('modal-group').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form action="/admin/users/save-group" method="POST" class="p-8 space-y-6">
            <input type="hidden" name="username" id="modal-username">
            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Selecione o Perfil</label>
                <select name="groupname" id="modal-groupname" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                    <?php foreach ($profiles as $p): ?>
                        <option value="<?= htmlspecialchars($p['name']) ?>">
                            <?= htmlspecialchars($p['name']) ?>
                            (<?= round($p['download_kbps'] / 1024, 1) ?>/<?= round($p['upload_kbps'] / 1024, 1) ?> Mbps)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-3 text-xs text-slate-400">Ao alterar o grupo, o usuário receberá os novos limites de banda e timeout na próxima conexão.</p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modal-group').classList.add('hidden')"
                        class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 font-semibold rounded-lg hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md">
                    Confirmar Mudança
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openGroupModal(username, currentGroup) {
    document.getElementById('modal-username').value = username;
    document.getElementById('modal-groupname').value = currentGroup;
    document.getElementById('modal-group').classList.remove('hidden');
}
</script>
