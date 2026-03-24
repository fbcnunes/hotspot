<div class="mb-10 flex justify-between items-end">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">Perfis de Grupo</h1>
        <p class="text-slate-500">Gerencie limites de velocidade e tempo para os grupos</p>
    </div>
    <button onclick="document.getElementById('modal-profile').classList.remove('hidden')" 
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow-sm transition">
        <i class="fas fa-plus mr-2"></i> Novo Perfil
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-slate-50 text-slate-500 text-sm uppercase">
            <tr>
                <th class="px-6 py-4">Nome do Perfil</th>
                <th class="px-6 py-4">Download (Mbps)</th>
                <th class="px-6 py-4">Upload (Mbps)</th>
                <th class="px-6 py-4">Session-Timeout</th>
                <th class="px-6 py-4">Idle-Timeout</th>
                <th class="px-6 py-4">Redirecionamento</th>
                <th class="px-6 py-4">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($profiles as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="px-6 py-4 text-slate-600"><?= round($p['download_kbps'] / 1024, 1) ?> Mbps</td>
                    <td class="px-6 py-4 text-slate-600"><?= round($p['upload_kbps'] / 1024, 1) ?> Mbps</td>
                    <td class="px-6 py-4 text-slate-600"><?= round($p['session_timeout_seconds'] / 60) ?> min</td>
                    <td class="px-6 py-4 text-slate-600"><?= round($p['idle_timeout_seconds'] / 60) ?> min</td>
                    <td class="px-6 py-4 text-slate-500 text-sm italic"><?= $p['redir_url'] ?: 'Padrão' ?></td>
                    <td class="px-6 py-4">
                        <button onclick='editProfile(<?= json_encode($p) ?>)' class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Profile -->
<div id="modal-profile" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden">
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modal-title" class="text-xl font-bold text-slate-800">Novo Perfil</h2>
            <button onclick="document.getElementById('modal-profile').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form action="/admin/profiles" method="POST" class="p-8 space-y-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nome do Grupo (RADIUS)</label>
                <input type="text" name="name" id="field-name" required placeholder="Ex: visitantes, vip, limitado"
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Download (Mbps)</label>
                    <input type="number" step="0.1" name="download_mbps" id="field-down" value="5"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Upload (Mbps)</label>
                    <input type="number" step="0.1" name="upload_mbps" id="field-up" value="2"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Session (Minutos)</label>
                    <input type="number" name="session_timeout_minutes" id="field-timeout" value="60"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Idle (Minutos)</label>
                    <input type="number" name="idle_timeout_minutes" id="field-idle" value="10"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">URL de Destino (Opcional)</label>
                <input type="url" name="redir_url" id="field-redir" placeholder="https://..."
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('modal-profile').classList.add('hidden')"
                        class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 font-semibold rounded-lg hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md">
                    Salvar Perfil
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editProfile(data) {
    document.getElementById('modal-title').textContent = 'Editar Perfil: ' + data.name;
    document.getElementById('field-name').value = data.name;
    document.getElementById('field-name').readOnly = true;
    document.getElementById('field-down').value = (data.download_kbps / 1024).toFixed(1);
    document.getElementById('field-up').value = (data.upload_kbps / 1024).toFixed(1);
    document.getElementById('field-timeout').value = Math.round(data.session_timeout_seconds / 60);
    document.getElementById('field-idle').value = Math.round(data.idle_timeout_seconds / 60);
    document.getElementById('field-redir').value = data.redir_url;
    document.getElementById('modal-profile').classList.remove('hidden');
}
</script>
