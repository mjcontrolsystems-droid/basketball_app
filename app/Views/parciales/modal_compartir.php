<?php
/**
 * Modal "Compartir" (navbar y footer). El QR y el enlace apuntan a la VISTA PRINCIPAL de
 * la copa/liga —no a la página suelta donde el visitante andaba—, porque es el link que
 * el organizador reparte: quien escanee debe caer en la portada del torneo, con su tabla,
 * su calendario y su menú, no en una subpágina sin contexto.
 *
 * Siempre se genera ABSOLUTO (https://dominio/slug/): un QR o un texto pegado en WhatsApp
 * con una ruta relativa no es un enlace que se pueda abrir.
 *
 * Una página puede ofrecer su propio enlace definiendo $compartir_url y $compartir_titulo
 * antes de incluir este archivo (lo hace la transmisión en vivo, ver partido_vivo.php).
 */
$compartirTorneo = $torneo ?? null;
$compartirUrl = $compartir_url ?? ($compartirTorneo ? url_copa_de($compartirTorneo) : SITE_ORIGIN . url('/'));
$compartirTitulo = $compartir_titulo ?? ($compartirTorneo ? $compartirTorneo['nombre'] : 'Plataforma de Copas y Ligas');
$compartirDescripcion = isset($compartir_url)
    ? 'Comparte esta vista escaneando el código o copiando el enlace.'
    : 'Comparte ' . ($compartirTorneo ? 'la página principal de esta copa o liga' : 'este sitio') . ' escaneando el código o copiando el enlace.';
?>
<div class="modal fade" id="modalCompartir" tabindex="-1" aria-hidden="true" data-url-compartir="<?= e($compartirUrl) ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-heading"><i class="bi bi-share-fill me-2"></i>Compartir</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2 text-center">
                <p class="fw-semibold mb-1"><?= e($compartirTitulo) ?></p>
                <p class="text-muted small mb-3"><?= e($compartirDescripcion) ?></p>
                <div id="qrCompartir" class="d-flex justify-content-center align-items-center mx-auto mb-3" style="width:200px;height:200px;background:#fff;border-radius:16px;border:1px solid rgba(123,47,247,.12);"></div>
                <div class="input-group">
                    <input type="text" id="inputEnlaceCompartir" class="form-control" readonly>
                    <button class="btn btn-degradado" type="button" id="btnCopiarEnlace"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                </div>
            </div>
        </div>
    </div>
</div>
