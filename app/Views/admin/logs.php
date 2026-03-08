<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Logs do Sistema</h1>
    <p class="text-slate-500">Arquivos de auditoria e erros registrados em storage/logs</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-100 bg-slate-50">
        <h2 class="font-bold text-slate-800">Arquivos Disponíveis</h2>
    </div>
    <div class="divide-y divide-slate-100">
        <?php if (empty($files)): ?>
            <div class="p-10 text-center text-slate-400">Nenhum arquivo de log encontrado.</div>
        <?php endif; ?>
        <?php foreach ($files as $file): ?>
            <div class="p-6 hover:bg-slate-50 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="text-slate-400 mr-4"><i class="fas fa-file-alt text-2xl"></i></div>
                    <div>
                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($file) ?></div>
                        <div class="text-xs text-slate-400">Tipo: Log de Aplicação</div>
                    </div>
                </div>
                <a href="/admin/logs/view?file=<?= urlencode($file) ?>" 
                   class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 px-4 rounded-lg transition">
                    Visualizar Conteúdo
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
