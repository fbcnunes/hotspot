<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Gestão de Usuários</h1>
    <p class="text-slate-500">Visualize dados cadastrais e gerencie perfis de acesso individualmente</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
        <h2 class="font-bold text-slate-800">Cadastros Sincronizados (SQL Server)</h2>
        <span class="text-xs text-slate-400">Total: <?= count($users) ?> usuários</span>
    </div>
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
                                <?= $u['groupname'] ?: 'padrao' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-sm">
                            <?= $u['last_login'] ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick='openGroupModal("<?= $u['username'] ?>", "<?= $u['groupname'] ?: 'padrao' ?>")' 
                                    class="bg-slate-100 hover:bg-blue-600 hover:text-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold transition">
                                Alterar Grupo
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Group Change -->
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
