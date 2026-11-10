<?php
/* ══════════════════════════════════════════
   REVIEWS SECTION — included from product.php
   Save as: C:\xampp\htdocs\LectroHub\customer\reviews_section.php

   Expects these variables to already exist in scope (product.php
   sets all of them before including this file):
     $conn            — PDO connection
     $id              — (int) the product being viewed
     $rating_summary  — ['avg' => float, 'count' => int], from get_product_rating_summary()
   Uses $_SESSION['user'] if present.

   The ?? fallbacks below are just so an IDE/static analyzer doesn't flag
   $id / $rating_summary as "undefined" when it looks at this file in
   isolation — product.php always sets both before including this file.
══════════════════════════════════════════ */

$product_id = $id ?? 0;
$rw_summary = $rating_summary ?? ['avg' => 0.0, 'count' => 0];
$rw_reviews = get_product_reviews($conn, $product_id);
$rw_user_id = $_SESSION['user']['id'] ?? null;
$rw_eligible_order_id = $rw_user_id ? get_reviewable_order_id($conn, (int)$rw_user_id, $product_id) : null;

function rw_stars(float $rating, int $size = 14): string
{
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= round($rating) ? 'var(--accent,#00e5ff)' : 'var(--border,#242c3d)';
        $out .= "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"$fill\" stroke=\"none\"><path d=\"M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\"/></svg>";
    }
    return $out;
}
?>
<section class="co-card" id="reviews" style="margin-top:24px">
    <div class="co-card-hd">Customer Reviews</div>

    <!-- ── SUMMARY ── -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        <div style="font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:var(--accent,#00e5ff)">
            <?= number_format($rw_summary['avg'], 1) ?>
        </div>
        <div>
            <div style="display:flex;gap:2px"><?= rw_stars($rw_summary['avg'], 16) ?></div>
            <div style="font-size:12px;color:var(--muted)">
                <?= $rw_summary['count'] ?> <?= $rw_summary['count'] === 1 ? 'review' : 'reviews' ?>
            </div>
        </div>
    </div>

    <!-- ── WRITE A REVIEW ── -->
    <?php if ($rw_eligible_order_id): ?>
        <form id="reviewForm" style="border:1px solid var(--border,#242c3d);border-radius:10px;padding:16px;margin-bottom:24px">
            <input type="hidden" id="rvProductId" value="<?= $product_id ?>">
            <input type="hidden" id="rvOrderId" value="<?= $rw_eligible_order_id ?>">

            <div class="form-label" style="margin-bottom:8px">Your rating *</div>
            <div id="starPicker" style="display:flex;gap:4px;margin-bottom:14px;cursor:pointer">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <svg class="rv-star" data-val="<?= $i ?>" width="26" height="26" viewBox="0 0 24 24"
                         fill="var(--border,#242c3d)" stroke="none" style="transition:fill .12s">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                <?php endfor; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Your review <span style="color:var(--muted);font-weight:400">(optional)</span></label>
                <textarea id="rvComment" class="form-input form-textarea" maxlength="2000"
                          placeholder="What did you think of this product?"></textarea>
            </div>

            <button type="submit" class="btn-checkout" style="max-width:200px" id="rvSubmitBtn">Submit Review</button>
        </form>
    <?php elseif ($rw_user_id): ?>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px">
            You can review this product once your order for it has been delivered.
        </p>
    <?php else: ?>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px">
            <a href="/LectroHub/auth/signin.php" style="color:var(--accent,#00e5ff)">Sign in</a> to review this product after purchase.
        </p>
    <?php endif; ?>

    <!-- ── REVIEW LIST ── -->
    <div id="reviewsList">
        <?php if (empty($rw_reviews)): ?>
            <p id="noReviewsMsg" style="font-size:13px;color:var(--muted)">No reviews yet — be the first!</p>
        <?php else: ?>
            <?php foreach ($rw_reviews as $rv): ?>
                <div class="review-item" id="review<?= $rv['id'] ?>"
                     style="padding:14px 0;border-top:1px solid var(--border,#242c3d)">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
                        <div>
                            <div style="display:flex;gap:2px;margin-bottom:4px"><?= rw_stars((float)$rv['rating'], 14) ?></div>
                            <div style="font-size:13px;font-weight:700"><?= htmlspecialchars($rv['full_name']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= date('M j, Y', strtotime($rv['created_at'])) ?></div>
                        </div>
                        <?php if ($rw_user_id && (int)$rv['user_id'] === (int)$rw_user_id): ?>
                            <button type="button" class="btn-danger-sm rv-delete" data-id="<?= $rv['id'] ?>"
                                    style="font-size:11px;padding:4px 9px">Delete</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($rv['body'])): ?>
                        <p style="font-size:13px;color:var(--txt,inherit);margin-top:8px"><?= nl2br(htmlspecialchars($rv['body'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    const starPicker = document.getElementById('starPicker');
    const form = document.getElementById('reviewForm');
    let selectedRating = 0;

    if (starPicker) {
        const stars = starPicker.querySelectorAll('.rv-star');
        function paint(val) {
            stars.forEach(s => {
                s.setAttribute('fill', s.dataset.val <= val ? 'var(--accent,#00e5ff)' : 'var(--border,#242c3d)');
            });
        }
        stars.forEach(s => {
            s.addEventListener('mouseenter', () => paint(s.dataset.val));
            s.addEventListener('click', () => { selectedRating = parseInt(s.dataset.val, 10); paint(selectedRating); });
        });
        starPicker.addEventListener('mouseleave', () => paint(selectedRating));
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (selectedRating < 1) {
                alert('Please choose a star rating.');
                return;
            }

            const btn = document.getElementById('rvSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Submitting…';

            const fd = new FormData();
            fd.append('action', 'submit');
            fd.append('product_id', document.getElementById('rvProductId').value);
            fd.append('order_id', document.getElementById('rvOrderId').value);
            fd.append('rating', selectedRating);
            fd.append('comment', document.getElementById('rvComment').value);

            fetch('review_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Something went wrong.');
                        btn.disabled = false;
                        btn.textContent = 'Submit Review';
                        return;
                    }
                    // Simplest reliable way to reflect the new review + updated
                    // eligibility/summary everywhere on the page.
                    location.reload();
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Submit Review';
                });
        });
    }

    document.querySelectorAll('.rv-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete your review?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('review_id', this.dataset.id);

            fetch('review_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { alert(data.message || 'Could not delete.'); return; }
                    location.reload();
                });
        });
    });
})();
</script>