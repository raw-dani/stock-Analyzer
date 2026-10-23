<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $symbol */
/** @var string $stockName */
/** @var string $timeFrame */
/** @var string $strategyName */
/** @var string $category */
/** @var string $analysisDate */
/** @var string $targetElementId */
/** @var array $metrics */
/** @var app\models\BlogPost|null $existingPost */
/** @var string $buttonClass */
/** @var string $widgetId */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$uniqueId = 'quick-blog-' . md5($symbol . $timeFrame . $strategyName . $analysisDate);
$strategyLabel = ucwords(str_replace('-', ' ', $strategyName));
$hasExisting = ($existingPost !== null);
$activeMode = \app\services\OpenAiBlogService::getGenerationMode();
$isZeroCost = ($activeMode === \app\services\OpenAiBlogService::MODE_ZERO_COST);
$activeModel = \app\services\OpenAiBlogService::getModel();
?>

<div id="<?= $uniqueId ?>-container" class="d-inline-flex align-items-center gap-1">
    <?php if ($hasExisting): ?>
        <div class="btn-group btn-group-sm">
            <a href="<?= Url::to(['/blog/view', 'slug' => $existingPost->slug_id]) ?>" 
               target="_blank" 
               class="btn btn-success btn-sm shadow-sm"
               title="Lihat artikel blog yang sudah dipublikasikan untuk kombinasi ini">
                <i class="bi bi-check2-circle me-1"></i> Sudah Diposting (<?= Html::encode($timeFrame) ?> <?= Html::encode($strategyLabel) ?>)
            </a>
            <button type="button" 
                    class="btn btn-outline-success btn-sm dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false" 
                    title="Opsi artikel">
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item" href="<?= Url::to(['/blog/view', 'slug' => $existingPost->slug_id]) ?>" target="_blank">
                        <i class="bi bi-eye text-info me-2"></i> Buka Artikel Publik
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= Url::to(['/blog/update', 'id' => $existingPost->id]) ?>">
                        <i class="bi bi-pencil-square text-primary me-2"></i> Edit di Admin
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-warning" onclick="triggerBlogQuickPost('<?= $uniqueId ?>', true)">
                        <i class="bi bi-arrow-repeat me-2"></i> Generate Ulang (Overwrite)
                    </button>
                </li>
            </ul>
        </div>
    <?php else: ?>
        <button type="button" 
                id="<?= $uniqueId ?>-btn" 
                class="<?= Html::encode($buttonClass) ?>" 
                onclick="triggerBlogQuickPost('<?= $uniqueId ?>', false)"
                title="<?= $isZeroCost ? 'Mode 100% Gratis: 0 Token & $0 Biaya API' : 'Mode Cloud AI: ' . Html::encode($activeModel) ?>">
            <?php if ($isZeroCost): ?>
                <i class="bi bi-lightning-charge-fill text-success me-1"></i>
            <?php else: ?>
                <i class="bi bi-robot text-primary me-1"></i>
            <?php endif; ?>
            Post Analisa ke Blog Info
            <?php if ($isZeroCost): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size: 0.7rem;">$0 Token</span>
            <?php endif; ?>
        </button>
    <?php endif; ?>
</div>

<!-- Modal Progress Generator Cepat Blog -->
<?php if (!isset($this->params['blogQuickPostModalRegistered'])): ?>
    <?php $this->params['blogQuickPostModalRegistered'] = true; ?>
    <div class="modal fade" id="blogQuickPostModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold" id="blogModalTitle">
                        <i class="bi bi-robot me-2"></i> Publikasi Analisis Saham AI
                    </h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <div id="blogModalLoading">
                        <div class="spinner-border text-primary my-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="fw-bold text-dark mt-2" id="blogModalStepText">Menginisialisasi...</h5>
                        <p class="text-muted small mb-0" id="blogModalSubText">Harap tunggu sebentar, sistem sedang memproses analisis.</p>
                    </div>

                    <div id="blogModalSuccess" class="d-none py-3">
                        <div class="text-success mb-3">
                            <i class="bi bi-check-circle-fill" style="font-size: 3.5rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Artikel Berhasil Dipublikasikan!</h4>
                        <p class="text-muted small mb-4" id="blogModalSuccessMsg">
                            Analisis saham dwibahasa dan gambar chart WebP telah tersimpan ke Blog Info Saham.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="#" id="blogModalViewBtn" target="_blank" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Lihat Artikel Blog
                            </a>
                            <a href="#" id="blogModalEditBtn" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </a>
                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">
                                Tutup
                            </button>
                        </div>
                    </div>

                    <div id="blogModalError" class="d-none py-3">
                        <div class="text-danger mb-3">
                            <i class="bi bi-exclamation-triangle-fill" style="font-size: 3.5rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Gagal Membuat Artikel</h4>
                        <p class="text-danger small mb-4" id="blogModalErrorMsg"></p>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Html2Canvas loader & Generator Trigger -->
    <script>
    (function() {
        if (!window.html2canvasLoaded) {
            window.html2canvasLoaded = false;
            var script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            script.async = true;
            script.onload = function() {
                window.html2canvasLoaded = true;
            };
            document.head.appendChild(script);
        }
    })();

    window.blogQuickPostConfigs = window.blogQuickPostConfigs || {};
    </script>
<?php endif; ?>

<script>
window.blogQuickPostConfigs['<?= $uniqueId ?>'] = {
    symbol: '<?= Html::encode($symbol) ?>',
    stockName: '<?= Html::encode($stockName) ?>',
    timeFrame: '<?= Html::encode($timeFrame) ?>',
    strategyName: '<?= Html::encode($strategyName) ?>',
    category: '<?= Html::encode($category) ?>',
    analysisDate: '<?= Html::encode($analysisDate) ?>',
    targetElementId: '<?= Html::encode($targetElementId) ?>',
    metrics: <?= json_encode($metrics) ?>
};

function triggerBlogQuickPost(uniqueId, isOverwrite) {
    var config = window.blogQuickPostConfigs[uniqueId];
    if (!config) return;

    var modalEl = document.getElementById('blogQuickPostModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    var loadingDiv = document.getElementById('blogModalLoading');
    var successDiv = document.getElementById('blogModalSuccess');
    var errorDiv = document.getElementById('blogModalError');
    var stepText = document.getElementById('blogModalStepText');
    var subText = document.getElementById('blogModalSubText');

    loadingDiv.classList.remove('d-none');
    successDiv.classList.add('d-none');
    errorDiv.classList.add('d-none');

    stepText.innerText = 'Langkah 1/3: 📸 Mengambil tangkapan layar visual...';
    subText.innerText = 'Mengonversi elemen grafik chart menjadi gambar WebP resolusi tinggi.';
    modal.show();

    // Pastikan html2canvas siap
    function doCapture() {
        var targetEl = null;
        if (config.targetElementId) {
            targetEl = document.getElementById(config.targetElementId);
        }
        if (!targetEl) {
            // Fallback ke container chart terdekat
            targetEl = document.getElementById('chart-stock') || 
                       document.getElementById('rsi-signal-chart') || 
                       document.getElementById('ma-chart') || 
                       document.querySelector('.card.border-0.shadow-sm.mb-4') ||
                       document.body;
        }

        var capturePromise;
        if (window.html2canvas && targetEl) {
            capturePromise = html2canvas(targetEl, {
                useCORS: true,
                scale: 1.5,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                return canvas.toDataURL('image/png');
            }).catch(function(e) {
                console.warn('html2canvas capture warning:', e);
                return '';
            });
        } else {
            capturePromise = Promise.resolve('');
        }

        capturePromise.then(function(screenshotBase64) {
            <?php if ($isZeroCost): ?>
            stepText.innerText = 'Langkah 2/3: ⚡ Meracik riset analisis E-E-A-T (Mode 100% Gratis / 0 Token)...';
            subText.innerText = 'Menyusun laporan kuantitatif institusional dwibahasa secara instan tanpa biaya API...';
            <?php else: ?>
            stepText.innerText = 'Langkah 2/3: 🤖 Meracik riset AI <?= Html::encode($activeModel) ?> (Token-Optimized)...';
            subText.innerText = 'Mengirim metrik terkompresi hemat token ke AI dan merumuskan Trading Plan...';
            <?php endif; ?>

            var formData = new FormData();
            formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');
            formData.append('symbol', config.symbol);
            formData.append('stock_name', config.stockName);
            formData.append('time_frame', config.timeFrame);
            formData.append('strategy_name', config.strategyName);
            formData.append('category', config.category);
            formData.append('analysis_date', config.analysisDate);
            formData.append('metrics', JSON.stringify(config.metrics));
            formData.append('screenshot', screenshotBase64);
            formData.append('overwrite', isOverwrite ? '1' : '0');

            return fetch('<?= Url::to(['/blog/generate-from-stock']) ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (data.success) {
                loadingDiv.classList.add('d-none');
                successDiv.classList.remove('d-none');
                document.getElementById('blogModalSuccessMsg').innerText = data.message || 'Artikel berhasil diterbitkan!';
                document.getElementById('blogModalViewBtn').href = data.url;
                document.getElementById('blogModalEditBtn').href = data.editUrl;

                // Ganti tombol di halaman secara instan menjadi badge sukses
                var container = document.getElementById(uniqueId + '-container');
                if (container) {
                    container.innerHTML = '<div class="btn-group btn-group-sm">' +
                        '<a href="' + data.url + '" target="_blank" class="btn btn-success btn-sm shadow-sm">' +
                        '<i class="bi bi-check2-circle me-1"></i> Sudah Diposting (' + config.timeFrame + ')' +
                        '</a>' +
                        '</div>';
                }
            } else {
                throw new Error(data.message || 'Terjadi kesalahan sistem saat memproses artikel.');
            }
        })
        .catch(function(err) {
            loadingDiv.classList.add('d-none');
            errorDiv.classList.remove('d-none');
            document.getElementById('blogModalErrorMsg').innerText = err.message || 'Koneksi gagal.';
        });
    }

    if (window.html2canvasLoaded || window.html2canvas) {
        setTimeout(doCapture, 300);
    } else {
        var attempts = 0;
        var interval = setInterval(function() {
            attempts++;
            if (window.html2canvas || attempts > 15) {
                clearInterval(interval);
                doCapture();
            }
        }, 200);
    }
}
</script>
