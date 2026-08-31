// ======================================
// SAFE SCRIPT LOADER (no duplicates)
// ======================================
function loadScript(url) {
    return new Promise((resolve, reject) => {

        // prevent duplicate loads
        if (document.querySelector(`script[src="${url}"]`)) {
            return resolve();
        }

        const script = document.createElement('script');
        script.src = url;
        script.async = false; // 🔥 ensures order
        script.onload = resolve;
        script.onerror = reject;

        document.head.appendChild(script);
    });
}

// ======================================
// LOAD CODEMIRROR PROPERLY (ORDER FIXED)
// ======================================
async function initCodeMirror() {
    try {
        await loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.js');

        // load modes in parallel AFTER core
        await Promise.all([
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/xml/xml.min.js'),
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/javascript/javascript.min.js'),
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/css/css.min.js'),
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/htmlmixed/htmlmixed.min.js')
        ]);

        console.log("✅ CodeMirror fully loaded");

        // 🔥 ONLY init AFTER everything is ready
        window.EditorManager.initAll();

    } catch (error) {
        console.error("❌ Failed to load CodeMirror:", error);
    }
}

// ======================================
// EDITOR MANAGER (FIXED + SAFE)
// ======================================
window.EditorManager = {
    instances: [],

    initAll() {

        // 🔥 guard (prevents crash)
        if (typeof CodeMirror === 'undefined') {
            console.warn('CodeMirror not ready yet');
            return;
        }

        const editors = document.querySelectorAll('[data-code-editor]');

        editors.forEach((el) => {

            if (el._cm) return;

            const textarea = el.querySelector('textarea');
            if (!textarea) return;

            const cm = CodeMirror.fromTextArea(textarea, {
                mode: "htmlmixed", // 🔥 FIXED
                lineNumbers: true,
                lineWrapping: true,
                viewportMargin: Infinity
            });

            el._cm = cm;
            this.instances.push(cm);

            // 🔥 expose global editor (needed for media insert)
            if (!window.editor) {
                window.editor = cm;
            }

            // refresh fixes
            setTimeout(() => cm.refresh(), 100);
            setTimeout(() => cm.refresh(), 300);
        });

        // 🔥 keep layout responsive
        window.addEventListener('resize', () => {
            this.instances.forEach(cm => cm.refresh());
        });

        // 🔥 safety fallback
        if (!window.editor && this.instances.length) {
            window.editor = this.instances[0];
        }
    }
};

// ======================================
// GLOBAL EDITOR INSERT (MEDIA PANEL)
// ======================================
window.insertMediaToEditor = function(tag) {

    // CodeMirror
    if (window.editor && typeof window.editor.replaceSelection === 'function') {
        window.editor.replaceSelection(tag);
        return;
    }

    // textarea fallback
    const textarea = document.querySelector('textarea[name="body"]');

    if (textarea) {
        const start = textarea.selectionStart || 0;
        const end   = textarea.selectionEnd || 0;

        textarea.value =
            textarea.value.substring(0, start) +
            tag +
            textarea.value.substring(end);

        textarea.focus();
        return;
    }

    // fallback → copy
    console.warn('No editor found → copying instead');
    navigator.clipboard.writeText(tag);
};

// ======================================
// INIT ENTRY POINT (FIXED ORDER)
// ======================================
document.addEventListener('DOMContentLoaded', function () {
    initCodeMirror(); // 🔥 correct entry
});

// ======================================
// FORM HANDLER (UNCHANGED + SAFE)
// ======================================
(function () {

    const form = document.getElementById('pageForm');
    if (!form) return;

    function showToast(message, type = 'success') {
        let toast = document.createElement('div');
        toast.innerText = message;

        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.padding = '10px 16px';
        toast.style.borderRadius = '8px';
        toast.style.color = '#fff';
        toast.style.fontSize = '14px';
        toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
        toast.style.zIndex = '9999';

        toast.style.background = type === 'error'
            ? '#dc2626'
            : '#16a34a';

        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 2500);
    }

    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('pageForm');
        const saveBtn = document.getElementById('saveBtn');
        const deleteForm = document.getElementById('deleteForm');

        // ================= SAVE (ONLY BUTTON CLICK) =================
        if (saveBtn && form) {

            saveBtn.addEventListener('click', async function () {

                // sync CodeMirror
                if (window.EditorManager?.instances) {
                    window.EditorManager.instances.forEach(cm => cm.save());
                }

                const formData = new FormData(form);

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await res.json();

                    if (data.success) {
                        showToast('Saved successfully ✅');
                    } else {
                        showToast(data.message || 'Save failed ❌', 'error');
                    }

                } catch (err) {
                    console.error(err);
                    showToast('Network error ❌', 'error');
                }

            });
        }

        // ================= DELETE (PURE FORM) =================
        if (deleteForm) {
            deleteForm.addEventListener('submit', function (e) {

                const confirmText = prompt('Type DELETE to confirm');

                if (confirmText !== 'DELETE') {
                    e.preventDefault();
                    showToast('Delete cancelled', 'error');
                }
            });
        }

    });

})();