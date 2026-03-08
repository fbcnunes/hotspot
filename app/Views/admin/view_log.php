<div class="mb-10 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">Visualizando Log</h1>
        <p class="text-slate-500"><?= htmlspecialchars($filename) ?></p>
    </div>
    <a href="/admin/logs" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-6 py-2 rounded-lg font-semibold transition">
        <i class="fas fa-arrow-left mr-2"></i> Voltar
    </a>
</div>

<div class="bg-slate-900 rounded-xl shadow-2xl overflow-hidden border border-slate-800">
    <div class="px-6 py-3 bg-slate-800 border-b border-slate-700 flex justify-between items-center">
        <span class="text-slate-400 text-xs font-mono">Terminal – UTF-8</span>
        <div class="flex space-x-2">
            <div class="w-3 h-3 rounded-full bg-rose-500"></div>
            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
        </div>
    </div>
    <div class="p-6 overflow-auto max-h-[600px]">
        <pre class="text-slate-300 font-mono text-sm leading-relaxed"><?= htmlspecialchars($content) ?></pre>
    </div>
</div>
