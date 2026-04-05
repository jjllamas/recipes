    <footer class="mt-5 pt-4 pb-3 border-top text-center text-muted small">
        🍽️ Recipe Planner &mdash; <?= date('Y') ?>
    </footer>
</div><!-- /.container -->

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(message, type = 'success') {
    const bg = type === 'success' ? 'bg-success' : 'bg-danger';
    const id = 'toast-' + Date.now();
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    bootstrap.Toast.getOrCreateInstance(document.getElementById(id), { delay: 3000 }).show();
}
</script>
</body>
</html>
