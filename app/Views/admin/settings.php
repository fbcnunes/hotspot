<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-800">Configurações</h1>
    <p class="text-slate-500">Parâmetros globais e customização visual do portal</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Banner Management -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="font-bold text-slate-800 flex items-center">
                <i class="fas fa-image mr-2 text-blue-500"></i> Banner do Portal
            </h2>
        </div>
        
        <div class="p-8 flex-1 space-y-8">
            <!-- Upload Form -->
            <form action="/admin/settings/upload-banner" method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Novo Banner (Auto-ajuste 9:16 - 1080x1920)</label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition relative overflow-hidden">
                            <div id="upload-placeholder" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-400 mb-2"></i>
                                <p class="mb-2 text-sm text-slate-500"><span class="font-semibold">Clique para selecionar</span></p>
                                <p class="text-xs text-slate-400">PNG, JPG ou WebP</p>
                            </div>
                            <!-- Live Preview Area -->
                            <div id="preview-container" class="hidden absolute inset-0 bg-white">
                                <img id="image-preview" src="#" class="w-full h-full object-contain" alt="Preview">
                                <div class="absolute inset-0 border-4 border-blue-500 border-dashed pointer-events-none opacity-50"></div>
                            </div>
                            <input id="banner_input" name="banner_file" type="file" class="hidden" accept="image/*" />
                        </label>
                    </div>
                </div>

                <div id="upload-actions" class="hidden flex flex-col gap-2">
                    <p class="text-xs text-blue-600 font-medium flex items-center">
                        <i class="fas fa-info-circle mr-1"></i> 
                        A imagem acima é apenas um preview. O corte central em 9:16 (1080x1920) será aplicado ao salvar.
                    </p>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700 transition shadow-md">
                            Confirmar e Salvar Banner
                        </button>
                        <button type="button" onclick="cancelPreview()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                    </div>
                </div>
            </form>

            <div class="border-t pt-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">URL Atual do Banner</label>
                <form action="/admin/settings" method="POST" class="flex gap-2">
                    <input type="text" name="banner_url" value="<?= htmlspecialchars($settings['banner_url'] ?? '') ?>" 
                           class="flex-1 px-4 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 focus:outline-none">
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">
                        Atualizar
                    </button>
                    <input type="hidden" name="global_redir_url" value="<?= htmlspecialchars($settings['global_redir_url'] ?? '') ?>">
                </form>
            </div>

            <?php if (!empty($settings['banner_url'])): ?>
                <div class="border-t pt-6">
                    <span class="text-xs font-bold text-slate-400 uppercase mb-3 block">Banner Ativo (Proporção 9:16):</span>
                    <div class="relative w-full max-w-[200px] mx-auto overflow-hidden rounded-lg shadow-md border border-slate-200 aspect-[9/16]">
                        <img src="<?= htmlspecialchars($settings['banner_url']) ?>?t=<?= time() ?>" alt="Banner Ativo" class="absolute inset-0 w-full h-full object-cover">
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- General Settings -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="font-bold text-slate-800 flex items-center">
                <i class="fas fa-cog mr-2 text-blue-500"></i> Geral
            </h2>
        </div>
        
        <form action="/admin/settings" method="POST" class="p-8 space-y-6">
            <input type="hidden" name="banner_url" value="<?= htmlspecialchars($settings['banner_url'] ?? '') ?>">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">URL de Redirecionamento Global</label>
                <input type="text" name="global_redir_url" value="<?= htmlspecialchars($settings['global_redir_url'] ?? '') ?>"
                       placeholder="https://www.suaempresa.com.br"
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="mt-2 text-xs text-slate-400">Página exibida após o login caso o perfil do usuário não defina uma URL específica.</p>
            </div>

            <div class="pt-6 border-t flex justify-end">
                <button type="submit" class="w-full lg:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition shadow-md">
                    Salvar Configurações Gerais
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const bannerInput = document.getElementById('banner_input');
const previewContainer = document.getElementById('preview-container');
const imagePreview = document.getElementById('image-preview');
const placeholder = document.getElementById('upload-placeholder');
const actions = document.getElementById('upload-actions');

bannerInput.onchange = evt => {
    const [file] = bannerInput.files;
    if (file) {
        imagePreview.src = URL.createObjectURL(file);
        previewContainer.classList.remove('hidden');
        placeholder.classList.add('hidden');
        actions.classList.remove('hidden');
    }
}

function cancelPreview() {
    bannerInput.value = '';
    previewContainer.classList.add('hidden');
    placeholder.classList.remove('hidden');
    actions.classList.add('hidden');
    imagePreview.src = '#';
}
</script>
