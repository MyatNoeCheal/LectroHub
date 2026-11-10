<?php
/* ══════════════════════════════════════════
   ADMIN — REVIEWS
   Save as: C:\xampp\htdocs\LectroHub\admin\reviews.php

   Admin can VIEW and DELETE reviews only — no edit capability,
   by design (matches customer-side review_action.php, which
   also only ever supports 'submit' and 'delete').
══════════════════════════════════════════ */

require_once "auth_check.php";

$page_title = "Reviews — LectroHub Admin";

require_once "../lectrohub_db/connection.php";
if (!isset($_SESSION)) session_start();

function review_img(?string $img): string {
    if ($img && $img !== '') {
        return "img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/80x80/070a10/00e5ff?text=%20";
}

function short_body(?string $text, int $limit = 140): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text) <= $limit) return $text;
    return rtrim(mb_substr($text, 0, $limit)) . '…';
}

function stars_html(int $rating, int $size = 13): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= $rating ? '#f59e0b' : 'var(--border)';
        $out .= "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"$fill\" stroke=\"none\"><path d=\"M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\"/></svg>";
    }
    return $out;
}

/* ══════════════════════════════════════════
   URL BUILDER — keep current params, override some
══════════════════════════════════════════ */
function build_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    if (isset($overrides['status']) || isset($overrides['rating'])
        || isset($overrides['sort']) || isset($overrides['q'])) {
        $params['page'] = 1;
    }
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}

/* ══════════════════════════════════════════
   FILTERS FROM URL
══════════════════════════════════════════ */
$search_q       = trim($_GET['q'] ?? '');
$status_filter  = $_GET['status'] ?? '';
$rating_filter  = $_GET['rating'] ?? '';
$sort           = $_GET['sort'] ?? 'newest';
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 10;

/* ══════════════════════════════════════════
   QUICK STATS
══════════════════════════════════════════ */
$total_reviews = (int)$conn->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$avg_rating    = (float)$conn->query("SELECT COALESCE(AVG(rating), 0) FROM reviews")->fetchColumn();
$pending_count = (int)$conn->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();

/* ══════════════════════════════════════════
   REVIEWS QUERY
══════════════════════════════════════════ */
$where  = ["1=1"];
$params = [];

if ($search_q !== '') {
    $where[]       = "(p.product_name LIKE :q OR u.full_name LIKE :q2 OR r.body LIKE :q3)";
    $params[':q']  = "%$search_q%";
    $params[':q2'] = "%$search_q%";
    $params[':q3'] = "%$search_q%";
}

if ($status_filter !== '' && in_array($status_filter, ['pending', 'approved', 'rejected'], true)) {
    $where[]            = "r.status = :status";
    $params[':status']  = $status_filter;
}

if ($rating_filter !== '' && in_array((int)$rating_filter, [1, 2, 3, 4, 5], true)) {
    $where[]            = "r.rating = :rating";
    $params[':rating']  = (int)$rating_filter;
}

$where_sql = implode(' AND ', $where);

$order_sql = match ($sort) {
    'oldest'      => 'r.created_at ASC',
    'rating_high' => 'r.rating DESC, r.created_at DESC',
    'rating_low'  => 'r.rating ASC, r.created_at DESC',
    default       => 'r.created_at DESC',
};

$count_stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN users    u ON u.id = r.user_id
    WHERE $where_sql
");
$count_stmt->execute($params);
$total_matching = (int)$count_stmt->fetchColumn();
$total_pages    = max(1, (int)ceil($total_matching / $per_page));
$page           = min($page, $total_pages);
$offset         = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT
        r.id, r.rating, r.title, r.body, r.status, r.created_at,
        p.id AS product_id, p.product_name, p.image AS product_img,
        u.full_name AS customer_name, u.email AS customer_email
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN users    u ON u.id = r.user_id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_colors = [
    'approved' => ['bg' => 'rgba(16, 163, 104, .1)',  'fg' => 'var(--green)'],
    'pending'  => ['bg' => 'rgba(245, 158, 11, .12)',  'fg' => 'var(--amber)'],
    'rejected' => ['bg' => 'rgba(220, 38, 38, .08)',   'fg' => 'var(--red)'],
];

?>

<?php require_once "head.php"; ?>

<div class="sb-mobile-ovl" id="sbOvl" onclick="closeSb()"></div>

<div class="shell">

    <!-- SIDEBAR  -->
    <?php require_once "sidebar.php" ?>

    <!-- NAVBAR -->
    <?php require_once "navbar.php" ?>

    <main class="main">

        <!-- Page Header -->
        <div class="page-hd au">
            <div>
                <div class="ph-eyebrow">Catalogue</div>
                <h1 class="ph-title">Reviews</h1>
                <p class="ph-sub">Customer reviews across all products</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card au au-1" style="--sc:#00e5ff">
                <div class="sc-top">
                    <div class="sc-icon">📝</div>
                </div>
                <div class="sc-value"><?= number_format($total_reviews) ?></div>
                <div class="sc-label">Total Reviews</div>
            </div>
            <div class="stat-card au au-2" style="--sc:#f59e0b">
                <div class="sc-top">
                    <div class="sc-icon">⭐</div>
                </div>
                <div class="sc-value"><?= number_format($avg_rating, 1) ?></div>
                <div class="sc-label">Average Rating</div>
            </div>
            <div class="stat-card au au-3" style="--sc:#a78bfa">
                <div class="sc-top">
                    <div class="sc-icon">⏳</div>
                </div>
                <div class="sc-value"><?= number_format($pending_count) ?></div>
                <div class="sc-label">Pending</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form class="filter-bar" method="get" id="filterForm">
            <input type="text" name="q" placeholder="Search product, customer, or review text..."
                   value="<?= htmlspecialchars($search_q) ?>">

            <select name="status" onchange="document.getElementById('filterForm').submit()">
                <option value="">All statuses</option>
                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="pending"  <?= $status_filter === 'pending'  ? 'selected' : '' ?>>Pending</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>

            <select name="rating" onchange="document.getElementById('filterForm').submit()">
                <option value="">All ratings</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= (string)$rating_filter === (string)$i ? 'selected' : '' ?>>
                        <?= $i ?> star<?= $i > 1 ? 's' : '' ?>
                    </option>
                <?php endfor; ?>
            </select>

            <div class="filter-spacer"></div>

            <select name="sort" onchange="document.getElementById('filterForm').submit()">
                <option value="newest"      <?= $sort === 'newest'      ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest"      <?= $sort === 'oldest'      ? 'selected' : '' ?>>Oldest first</option>
                <option value="rating_high" <?= $sort === 'rating_high' ? 'selected' : '' ?>>Highest rating</option>
                <option value="rating_low"  <?= $sort === 'rating_low'  ? 'selected' : '' ?>>Lowest rating</option>
            </select>
        </form>

        <!-- Reviews Table -->
        <div class="products-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reviewsTbody">
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--muted);padding:30px 16px">
                                No reviews match these filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $rv):
                            $sc = $status_colors[$rv['status']] ?? ['bg' => 'var(--card2)', 'fg' => 'var(--muted)'];
                        ?>
                        <tr id="rvRow<?= $rv['id'] ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;min-width:180px">
                                    <img src="<?= review_img($rv['product_img']) ?>" alt=""
                                         style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0">
                                    <div>
                                        <div style="font-weight:600"><?= htmlspecialchars($rv['product_name'] ?? 'Deleted product') ?></div>
                                        <?php if ($rv['title']): ?>
                                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($rv['title']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($rv['customer_name'] ?? 'Deleted user') ?></div>
                                <?php if ($rv['customer_email']): ?>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($rv['customer_email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><div style="display:flex;gap:1px"><?= stars_html((int)$rv['rating']) ?></div></td>
                            <td style="max-width:280px">
                                <?php if ($rv['body']): ?>
                                    <span style="color:var(--txt)"><?= htmlspecialchars(short_body($rv['body'])) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--dim);font-style:italic">No written comment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>">
                                    <?= ucfirst($rv['status']) ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;color:var(--muted)"><?= date('M j, Y', strtotime($rv['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn-icon rv-admin-delete" data-id="<?= $rv['id'] ?>"
                                        title="Delete review" style="color:var(--red)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="pag-info">
                Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_matching) ?> of <?= number_format($total_matching) ?> reviews
            </div>
            <div class="pag-btns">
                <a href="<?= htmlspecialchars(build_url(['page' => max(1, $page - 1)])) ?>"
                   class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="<?= htmlspecialchars(build_url(['page' => $i])) ?>"
                       class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="<?= htmlspecialchars(build_url(['page' => min($total_pages, $page + 1)])) ?>"
                   class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">›</a>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once "footer.php"; ?>

<!-- TOAST -->
<div class="toast" id="toast">
    <span class="toast-icon" id="ti">✓</span>
    <span id="tm"></span>
</div>

<script>
    /* ── Sidebar ── */
    function toggleSb() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sbOvl').classList.toggle('show');
    }
    function closeSb() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sbOvl').classList.remove('show');
    }
    function toggleDrop(id) {
        document.querySelectorAll('.nav-dropdown.open').forEach(d => { if (d.id !== id) d.classList.remove('open'); });
        document.getElementById(id).classList.toggle('open');
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.nav-dropdown'))
            document.querySelectorAll('.nav-dropdown.open').forEach(d => d.classList.remove('open'));
    });

document.querySelectorAll('.rv-admin-delete').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('Delete this review? This cannot be undone.')) return;

        const id  = this.dataset.id;
        const row = document.getElementById('rvRow' + id);

        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('review_id', id);

        fetch('review_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Could not delete review.', '⚠');
                    return;
                }
                if (row) row.remove();
                showToast('Review deleted.');
            })
            .catch(() => showToast('Network error. Please try again.', '⚠'));
    });
});

function showToast(msg, icon = '✓') {
    const t = document.getElementById('toast');
    document.getElementById('ti').textContent = icon;
    document.getElementById('tm').textContent = msg;
    t.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
        }
    });
</script>

</body>
</html>