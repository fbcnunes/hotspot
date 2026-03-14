<?php
$filters = $userFilters ?? [
    'matricula' => '',
    'name' => '',
    'cpf' => '',
    'groupname' => '',
    'blocked_status' => 'all',
    'session_status' => 'all',
];
$paginationData = $pagination ?? ['page' => 1, 'pages' => 0, 'total' => 0, 'per_page' => 50];
$currentPage = max(1, (int) ($paginationData['page'] ?? 1));
$totalPages = max(0, (int) ($paginationData['pages'] ?? 0));
$totalUsers = (int) ($paginationData['total'] ?? 0);
$queryBase = http_build_query(array_filter([
    'matricula' => $filters['matricula'] ?? '',
    'name' => $filters['name'] ?? '',
    'cpf' => $filters['cpf'] ?? '',
    'groupname' => $filters['groupname'] ?? '',
    'blocked_status' => ($filters['blocked_status'] ?? 'all') !== 'all' ? $filters['blocked_status'] : '',
    'session_status' => ($filters['session_status'] ?? 'all') !== 'all' ? $filters['session_status'] : '',
], static fn ($value) => $value !== ''));
$returnQuery = $_SERVER['QUERY_STRING'] ?? '';
?>

<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Gestão de Usuários</h1>
    <p class="text-slate-500">Pesquise usuários por matrícula, nome, CPF, grupo, status de bloqueio ou sessão ativa.</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 bg-slate-50">
        <h2 class="font-bold text-slate-800">Filtros de Pesquisa</h2>
        <p class="text-sm text-slate-500 mt-1">A listagem só é carregada depois que pelo menos um filtro for informado.</p>
    </div>
    <form method="GET" action="/admin/users" class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-4">
        <div>
            <label for="filter-matricula" class="block text-sm font-semibold text-slate-700 mb-2">Matrícula</label>
            <input id="filter-matricula" type="text" name="matricula" value="<?= htmlspecialchars($filters['matricula'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Digite a matrícula">
        </div>
        <div>
            <label for="filter-name" class="block text-sm font-semibold text-slate-700 mb-2">Nome</label>
            <input id="filter-name" type="text" name="name" value="<?= htmlspecialchars($filters['name'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Digite o nome">
        </div>
        <div>
            <label for="filter-cpf" class="block text-sm font-semibold text-slate-700 mb-2">CPF</label>
            <input id="filter-cpf" type="text" name="cpf" value="<?= htmlspecialchars($filters['cpf'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Digite o CPF">
        </div>
        <div>
            <label for="filter-groupname" class="block text-sm font-semibold text-slate-700 mb-2">Grupo</label>
            <select id="filter-groupname" name="groupname" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Todos os grupos</option>
                <?php foreach ($profiles as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?>" <?= ($filters['groupname'] ?? '') === $p['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter-blocked-status" class="block text-sm font-semibold text-slate-700 mb-2">Status de Bloqueio</label>
            <select id="filter-blocked-status" name="blocked_status" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="all" <?= ($filters['blocked_status'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="blocked" <?= ($filters['blocked_status'] ?? 'all') === 'blocked' ? 'selected' : '' ?>>Bloqueados</option>
                <option value="unblocked" <?= ($filters['blocked_status'] ?? 'all') === 'unblocked' ? 'selected' : '' ?>>Não bloqueados</option>
            </select>
        </div>
        <div>
            <label for="filter-session-status" class="block text-sm font-semibold text-slate-700 mb-2">Sessão Ativa</label>
            <select id="filter-session-status" name="session_status" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="all" <?= ($filters['session_status'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="active" <?= ($filters['session_status'] ?? 'all') === 'active' ? 'selected' : '' ?>>Com sessão ativa</option>
                <option value="inactive" <?= ($filters['session_status'] ?? 'all') === 'inactive' ? 'selected' : '' ?>>Sem sessão ativa</option>
            </select>
        </div>
        <div class="flex items-end gap-3">
            <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md">Pesquisar</button>
            <a href="/admin/users" class="px-4 py-3 border border-slate-200 text-slate-600 font-semibold rounded-lg hover:bg-slate-50 text-center">Limpar</a>
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
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Último Login</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $isBlocked = (int) ($u['is_blocked'] ?? 0) === 1;
                        $activeSessions = (int) ($u['active_sessions_count'] ?? 0);
                        ?>
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
                            <td class="px-6 py-4 text-sm">
                                <div>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $isBlocked ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' ?>">
                                        <?= $isBlocked ? 'Bloqueado' : 'Não bloqueado' ?>
                                    </span>
                                </div>
                                <div class="mt-2 text-slate-500">
                                    Sessões ativas: <?= $activeSessions ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <?= htmlspecialchars((string) ($u['last_login'] ?? '---')) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 flex-wrap">
                                    <?php if ($activeSessions > 0): ?>
                                        <button type="button" onclick='openSessionsModal("<?= htmlspecialchars($u["username"], ENT_QUOTES) ?>")' class="bg-amber-50 hover:bg-amber-500 hover:text-white text-amber-700 px-3 py-2 rounded-lg text-sm font-bold transition">
                                            Desconectar Sessão
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isBlocked): ?>
                                        <form action="/admin/users/unblock" method="POST" onsubmit="return confirmUnblock(this);">
                                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                            <button type="submit" class="bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 px-3 py-2 rounded-lg text-sm font-bold transition">
                                                Desbloquear
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="/admin/users/disconnect" method="POST" onsubmit="return confirmBlock(this);">
                                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                            <button type="submit" class="bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 px-3 py-2 rounded-lg text-sm font-bold transition">
                                                Bloquear
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button onclick='openGroupModal("<?= htmlspecialchars($u["username"], ENT_QUOTES) ?>", "<?= htmlspecialchars($u["groupname"] ?: "padrao", ENT_QUOTES) ?>")' class="bg-slate-100 hover:bg-blue-600 hover:text-white text-blue-600 px-3 py-2 rounded-lg text-sm font-bold transition">
                                        Alterar Grupo
                                    </button>
                                </div>
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
                <button type="button" onclick="document.getElementById('modal-group').classList.add('hidden')" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 font-semibold rounded-lg hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md">
                    Confirmar Mudança
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-sessions" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden">
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Sessões Ativas</h2>
            <button onclick="closeSessionsModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div id="sessions-loading" class="text-slate-500">Carregando sessões...</div>
            <div id="sessions-error" class="hidden text-rose-600 text-sm mb-3"></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500 uppercase">
                        <tr>
                            <th class="py-2">ID</th>
                            <th class="py-2">Session ID</th>
                            <th class="py-2">IP NAS / Cliente</th>
                            <th class="py-2">Início</th>
                            <th class="py-2 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="sessions-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const sessionsReturnQuery = <?= json_encode($returnQuery, JSON_UNESCAPED_UNICODE); ?>;

function openGroupModal(username, currentGroup) {
    document.getElementById('modal-username').value = username;
    document.getElementById('modal-groupname').value = currentGroup;
    document.getElementById('modal-group').classList.remove('hidden');
}

function confirmBlock(form) {
    const username = form.querySelector('input[name="username"]').value;
    return window.confirm(`Bloquear ${username}? O próximo login será negado até desbloqueio.`);
}

function confirmUnblock(form) {
    const username = form.querySelector('input[name="username"]').value;
    return window.confirm(`Remover bloqueio de ${username}? O usuário poderá se autenticar novamente.`);
}

function closeSessionsModal() {
    document.getElementById('modal-sessions').classList.add('hidden');
}

async function openSessionsModal(username) {
    const modal = document.getElementById('modal-sessions');
    const loading = document.getElementById('sessions-loading');
    const error = document.getElementById('sessions-error');
    const body = document.getElementById('sessions-body');

    body.innerHTML = '';
    error.classList.add('hidden');
    error.textContent = '';
    loading.classList.remove('hidden');
    modal.classList.remove('hidden');

    try {
        const response = await fetch(`/admin/users/sessions?username=${encodeURIComponent(username)}`);
        const data = await response.json();

        if (!response.ok || data.status !== 'ok') {
            throw new Error(data.message || 'Falha ao carregar sessões.');
        }

        const sessions = data.sessions || [];
        if (sessions.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="py-4 text-slate-500">Nenhuma sessão ativa encontrada.</td></tr>';
            return;
        }

        body.innerHTML = sessions.map((session) => {
            const startedAt = session.acctstarttime || '---';
            return `
                <tr>
                    <td class="py-3">${session.radacctid}</td>
                    <td class="py-3 font-mono text-xs">${session.acctsessionid || '---'}</td>
                    <td class="py-3">${session.nasipaddress || '---'} / ${session.framedipaddress || '---'}</td>
                    <td class="py-3">${startedAt}</td>
                    <td class="py-3 text-right">
                        <form action="/admin/sessions/disconnect" method="POST" onsubmit="return confirmSessionDisconnect(${session.radacctid});">
                            <input type="hidden" name="radacctid" value="${session.radacctid}">
                            <input type="hidden" name="return_query" value="${escapeHtml(sessionsReturnQuery)}">
                            <button type="submit" class="bg-amber-50 hover:bg-amber-500 hover:text-white text-amber-700 px-3 py-2 rounded-lg text-xs font-bold transition">
                                Desconectar
                            </button>
                        </form>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        error.textContent = err.message || 'Falha ao carregar sessões.';
        error.classList.remove('hidden');
    } finally {
        loading.classList.add('hidden');
    }
}

function confirmSessionDisconnect(radacctId) {
    return window.confirm(`Desconectar apenas a sessão ${radacctId}?`);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
