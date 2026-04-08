<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@4.4.168/build/pdf.min.mjs" type="module"></script>
<script type="module">
import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.4.168/build/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.4.168/build/pdf.worker.min.mjs';

const targets = Array.from(document.querySelectorAll('[data-print-pdf]'));
const renderedPromises = [];

async function renderPdfTarget(target) {
    const url = target.dataset.pdfUrl;
    if (!url) return;
    const loading = target.querySelector('.pdf-print-loading');
    try {
        const pdf = await pdfjsLib.getDocument(url).promise;
        target.innerHTML = '';
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 1.5 });
            const wrapper = document.createElement('div');
            wrapper.className = 'pdf-page-card';
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            await page.render({ canvasContext: context, viewport }).promise;
            const label = document.createElement('div');
            label.className = 'label';
            label.textContent = `${target.dataset.pdfName || 'PDF document'} - page ${pageNumber}`;
            wrapper.appendChild(canvas);
            wrapper.appendChild(label);
            target.appendChild(wrapper);
        }
    } catch (error) {
        console.error('Unable to render PDF for print.', error);
        if (loading) {
            loading.textContent = 'PDF preview could not be rendered. Please use the download link.';
        } else {
            target.innerHTML = '<div class="pdf-print-loading">PDF preview could not be rendered. Please use the download link.</div>';
        }
    }
}

targets.forEach((target) => renderedPromises.push(renderPdfTarget(target)));
window.__maps2uPrintRenderReady = Promise.allSettled(renderedPromises);
window.addEventListener('beforeprint', () => window.__maps2uPrintRenderReady);
</script>
