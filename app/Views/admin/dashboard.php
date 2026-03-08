<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Dashboard</h1>
    <p class="text-slate-500">Visão geral do sistema em tempo real</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <div class="text-blue-500 bg-blue-50 p-3 rounded-lg"><i class="fas fa-plug text-xl"></i></div>
            <span class="text-2xl font-bold"><?= count($sessions) ?></span>
        </div>
        <h3 class="text-slate-500 font-medium">Usuários Online</h3>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <!-- Active Sessions -->
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h2 class="font-bold text-slate-800">Sessões Ativas</h2>
            <button onclick="window.location.reload()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                <i class="fas fa-sync-alt mr-1"></i> Atualizar
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 text-sm uppercase">
                    <tr>
                        <th class="px-6 py-4">Usuário / Nome</th>
                        <th class="px-6 py-4 text-center">CPF / Matrícula</th>
                        <th class="px-6 py-4">IP</th>
                        <th class="px-6 py-4">Início</th>
                        <th class="px-6 py-4">Consumo (D/U)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($sessions)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Nenhuma sessão ativa no momento.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $s): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($s['name'] ?: $s['username']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($s['username']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs block text-slate-600">CPF: <?= $s['cpf'] ?: '---' ?></span>
                                <span class="text-xs block text-slate-500">Mat: <?= $s['matricula'] ?: '---' ?></span>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= $s['framedipaddress'] ?></td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <?= (new DateTime($s['acctstarttime']))->format('H:i:s') ?>
                                <span class="block text-[10px]"><?= round($s['acctsessiontime'] / 60) ?> min online</span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <span class="block text-emerald-600 font-medium">↓ <?= round($s['acctinputoctets'] / 1024 / 1024, 2) ?> MB</span>
                                <span class="block text-blue-600 font-medium">↑ <?= round($s['acctoutputoctets'] / 1024 / 1024, 2) ?> MB</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Consumption History -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="font-bold text-slate-800">Top Consumo (Geral)</h2>
        </div>
        <div class="p-0">
            <div class="divide-y divide-slate-100">
                <?php if (empty($consumption)): ?>
                    <div class="p-6 text-center text-slate-400">Sem histórico disponível.</div>
                <?php endif; ?>
                <?php foreach ($consumption as $c): ?>
                    <div class="p-4 hover:bg-slate-50 flex justify-between items-center">
                        <div>
                            <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($c['name'] ?: $c['username']) ?></div>
                            <div class="text-[10px] text-slate-400">
                                <?= $c['username'] ?> • CPF: <?= $c['cpf'] ?: '---' ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-blue-600"><?= round(($c['download'] + $c['upload']) / 1024 / 1024, 1) ?> MB</div>
                            <div class="text-[10px] text-slate-400"><?= $c['session_count'] ?> sessões</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
