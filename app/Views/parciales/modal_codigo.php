<div class="modal fade" id="modalCodigo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-heading"><i class="bi bi-key-fill me-2"></i>Tengo un código</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">Escribe el código de 6 caracteres que te compartió el organizador de la copa o liga.</p>
                <form method="get" action="<?= url('codigo.php') ?>" class="d-flex gap-2">
                    <input type="text" name="c" class="form-control text-center text-uppercase fw-bold" style="letter-spacing:.2em;" maxlength="6" placeholder="ABC123" autocomplete="off" required>
                    <button type="submit" class="btn btn-degradado px-4">Ir</button>
                </form>
            </div>
        </div>
    </div>
</div>
