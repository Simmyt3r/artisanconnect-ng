<?php
require_once 'config.php';

// ── TRADE CATEGORIES LIST ────────────────────────────────────
$categories = ['Carpentry','Tailoring','Electrical','Plumbing','Painting','Welding',
                'Masonry','Hairdressing','Barbering','Cobbling','Weaving','Pottery',
                'Blacksmithing','Auto Mechanic','Tiling','Upholstery','Photography','Other'];

$states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno',
           'Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo',
           'Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa',
           'Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba',
           'Yobe','Zamfara'];

// ── SEARCH & FILTER ─────────────────────────────────────────
$trade = is_string($_GET['trade'] ?? null) ? trim($_GET['trade']) : '';
$state = is_string($_GET['state'] ?? null) ? trim($_GET['state']) : '';
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$minRat = is_numeric($_GET['rating'] ?? null) ? (float)$_GET['rating'] : 0;
$minRat = max(0, min(5, $minRat));
$searching = isset($_GET['browse']) || $trade !== '' || $state !== '' || $q !== '' || $minRat > 0;
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$perPage = $searching ? 9 : 6;

$where = ["a.verification_status = 'verified'", 'a.is_available = 1'];
$params = [];
if ($trade !== '') {
    $where[] = 'a.trade_category = ?';
    $params[] = $trade;
}
if ($state !== '') {
    $where[] = 'a.state_of_operation = ?';
    $params[] = $state;
}
if ($q !== '') {
    $needle = '%' . strtr(preg_replace('/\s+/', ' ', $q), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? ESCAPE '!'
        OR a.bio LIKE ? ESCAPE '!'
        OR a.trade_category LIKE ? ESCAPE '!'
        OR a.trade_subcategory LIKE ? ESCAPE '!'
        OR a.state_of_operation LIKE ? ESCAPE '!'
        OR a.lga_of_operation LIKE ? ESCAPE '!'
        OR EXISTS (
            SELECT 1 FROM services s WHERE s.artisan_id = a.profile_id AND s.is_active = 1
            AND (s.service_title LIKE ? ESCAPE '!' OR s.service_description LIKE ? ESCAPE '!')
        ))";
    $params = array_merge($params, array_fill(0, 8, $needle));
}
$from = 'FROM artisan_profiles a JOIN users u ON a.user_id = u.user_id';
$reviewJoin = 'LEFT JOIN (
    SELECT r.artisan_id, COUNT(*) AS total_reviews, AVG(r.overall_rating) AS average_rating
    FROM reviews r JOIN users reviewer ON reviewer.user_id = r.customer_user_id
    GROUP BY r.artisan_id
) ratings ON ratings.artisan_id = a.profile_id';
if ($minRat > 0) {
    $where[] = 'ratings.average_rating >= ?';
    $params[] = $minRat;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$totalPages = 1;
if ($searching) {
    $countFrom = $minRat > 0 ? "$from $reviewJoin" : $from;
    $cntStmt = db()->prepare("SELECT COUNT(*) $countFrom $whereSQL");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
}
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("
    SELECT a.profile_id, a.trade_category, a.state_of_operation, a.lga_of_operation,
           COALESCE(ratings.average_rating, 0) AS average_rating,
           COALESCE(ratings.total_reviews, 0) AS total_reviews,
           u.first_name, u.last_name, u.profile_picture
    $from $reviewJoin $whereSQL
    ORDER BY average_rating DESC, total_reviews DESC, a.profile_id ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$artisans = $stmt->fetchAll();

head('Find Skilled Artisans');
?>

<?php if (!$searching): ?>
<!-- HERO ─────────────────────────────────────────────────── -->
<section style="background:linear-gradient(135deg,var(--green) 0%,var(--green-dark) 60%,#004d26 100%);color:#fff;padding:4rem 1rem">
 <div class="ctr" style="text-align:center">
  <div style="font-size:2.8rem;margin-bottom:.5rem">🔨</div>
  <h1 style="font-size:2.4rem;font-weight:900;margin-bottom:.6rem;line-height:1.2">Nigeria's Artisan Marketplace</h1>
  <p style="font-size:1.1rem;opacity:.88;max-width:540px;margin:0 auto 2rem">Connect with verified, skilled artisans near you — carpenters, tailors, electricians &amp; more.</p>

  <!-- SEARCH FORM -->
  <form method="get" action="index.php" style="background:rgba(255,255,255,.12);backdrop-filter:blur(10px);border-radius:var(--r);padding:1.3rem;max-width:700px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr auto;gap:.8rem">
   <input type="hidden" name="browse" value="1">
   <input name="q" class="fc" aria-label="Name, skill, or location" placeholder="Name, skill, or location…" value="<?=e($q)?>">
   <select name="trade" class="fc" aria-label="Trade">
    <option value="">All Trades</option>
    <?php foreach($categories as $c): ?>
    <option value="<?=e($c)?>" <?=$trade===$c?'selected':''?>><?=e($c)?></option>
    <?php endforeach ?>
   </select>
   <button class="btn btn-g" type="submit">🔍 Search</button>
   <select name="state" class="fc" aria-label="State">
    <option value="">All States</option>
    <?php foreach($states as $s): ?>
    <option value="<?=e($s)?>" <?=$state===$s?'selected':''?>><?=e($s)?></option>
    <?php endforeach ?>
   </select>
   <select name="rating" class="fc" aria-label="Minimum rating">
    <option value="">Any Rating</option>
    <option value="4" <?=$minRat>=4?'selected':''?>>4★ & above</option>
    <option value="3" <?=($minRat>=3&&$minRat<4)?'selected':''?>>3★ & above</option>
   </select>
   <div></div>
  </form>
  <a href="index.php?browse=1" class="btn btn-g btn-sm" style="margin-top:1rem">Browse all artisans</a>
 </div>
</section>

<!-- STATS BAR ───────────────────────────────────────────── -->
<div style="background:var(--white);border-bottom:1px solid var(--border);padding:.8rem 0">
 <div class="ctr" style="display:flex;gap:2rem;justify-content:center;flex-wrap:wrap;text-align:center">
  <?php
  $totA = db()->query("SELECT COUNT(*) FROM artisan_profiles WHERE verification_status='verified' AND is_available=1")->fetchColumn();
  $totT = db()->query("SELECT COUNT(DISTINCT trade_category) FROM artisan_profiles WHERE verification_status='verified' AND is_available=1")->fetchColumn();
  $totB = db()->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
  ?>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totA)?></div><div style="font-size:.8rem;color:var(--light)">Available Verified Artisans</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totT)?></div><div style="font-size:.8rem;color:var(--light)">Trade Categories</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totB)?></div><div style="font-size:.8rem;color:var(--light)">Jobs Completed</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)">37</div><div style="font-size:.8rem;color:var(--light)">States &amp; FCT</div></div>
 </div>
</div>

<!-- FEATURED ───────────────────────────────────────────── -->
<?php if ($artisans): ?>
<section class="sec">
 <div class="ctr">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.5rem">
   <div>
    <h2 style="font-size:1.4rem;font-weight:800">Featured Artisans</h2>
    <p style="color:var(--light);font-size:.9rem">Verified artisans available for hire</p>
   </div>
   <a href="index.php?browse=1" class="btn btn-o btn-sm">View All →</a>
  </div>
  <div class="g3">
   <?php foreach($artisans as $a): artisanCard($a); endforeach ?>
  </div>
 </div>
</section>

<!-- HOW IT WORKS ─────────────────────────────────────────── -->
<section class="sec" style="background:var(--white)">
 <div class="ctr" style="text-align:center">
  <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:.4rem">How It Works</h2>
  <p style="color:var(--light);margin-bottom:2rem">Connect with the right artisan in 3 simple steps</p>
  <div class="g3">
   <?php foreach([
     ['🔍','Search & Discover','Browse verified artisans by trade and location. Filter by rating and state.'],
     ['📋','View & Book','Check portfolios, read reviews, then send a booking request directly.'],
     ['💬','Connect & Complete','Chat with your artisan, agree on pricing, and get the job done.']
   ] as [$ico,$t,$d]): ?>
   <div class="card" style="text-align:center;padding:2rem">
    <div style="font-size:2.5rem;margin-bottom:.8rem"><?=$ico?></div>
    <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:.4rem"><?=$t?></h3>
    <p style="font-size:.9rem;color:var(--light)"><?=$d?></p>
   </div>
   <?php endforeach ?>
  </div>
 </div>
</section>
<?php endif ?>

<?php else: // ── SEARCH RESULTS MODE ───────────────────────────── ?>
<div class="ph">
 <div class="ctr">
  <h1><?=$q !== '' || $trade !== '' || $state !== '' || $minRat > 0 ? 'Search Results' : 'Browse Artisans'?></h1>
  <p><?=$total?> artisan<?=$total!=1?'s':''?> found<?=$q?' for "'.e($q).'"':''?><?=$trade?' in '.e($trade):''?><?=$state?' · '.e($state):''?></p>
 </div>
</div>

<section class="sec">
 <div class="ctr">
  <!-- FILTER BAR -->
  <form method="get" style="background:var(--white);border-radius:var(--r);padding:1rem 1.2rem;box-shadow:var(--sh);margin-bottom:1.5rem;display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end">
   <input type="hidden" name="browse" value="1">
   <div class="fg" style="flex:1;min-width:150px;margin:0">
    <label for="filter-q" style="font-size:.8rem">Keyword</label>
    <input id="filter-q" name="q" class="fc" placeholder="Name, skill, or location…" value="<?=e($q)?>">
   </div>
   <div class="fg" style="flex:1;min-width:140px;margin:0">
    <label for="filter-trade" style="font-size:.8rem">Trade</label>
    <select id="filter-trade" name="trade" class="fc">
     <option value="">All</option>
     <?php foreach($categories as $c): ?><option value="<?=e($c)?>" <?=$trade===$c?'selected':''?>><?=e($c)?></option><?php endforeach ?>
    </select>
   </div>
   <div class="fg" style="flex:1;min-width:130px;margin:0">
    <label for="filter-state" style="font-size:.8rem">State</label>
    <select id="filter-state" name="state" class="fc">
     <option value="">All</option>
     <?php foreach($states as $s): ?><option value="<?=e($s)?>" <?=$state===$s?'selected':''?>><?=e($s)?></option><?php endforeach ?>
    </select>
   </div>
   <div class="fg" style="min-width:130px;margin:0">
    <label for="filter-rating" style="font-size:.8rem">Min Rating</label>
    <select id="filter-rating" name="rating" class="fc">
     <option value="">Any</option>
     <option value="4" <?=$minRat>=4?'selected':''?>>4★+</option>
     <option value="3" <?=($minRat>=3&&$minRat<4)?'selected':''?>>3★+</option>
    </select>
   </div>
   <button class="btn btn-p" type="submit">Apply</button>
   <a href="index.php?browse=1" class="btn btn-o">Clear</a>
  </form>

  <?php if ($artisans): ?>
  <div class="g3">
   <?php foreach($artisans as $a): artisanCard($a); endforeach ?>
  </div>
  <!-- PAGINATION -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:.4rem;margin-top:2rem;flex-wrap:wrap">
   <?php
   $query = ['browse'=>1, 'q'=>$q, 'trade'=>$trade, 'state'=>$state, 'rating'=>$minRat ?: ''];
   for ($p=1; $p<=$totalPages; $p++): ?>
   <a href="?<?=e(http_build_query($query + ['page'=>$p]))?>"<?=$p===$page?' aria-current="page"':''?> class="btn <?=$p===$page?'btn-p':'btn-o'?> btn-sm"><?=$p?></a>
   <?php endfor ?>
  </div>
  <?php endif ?>
  <?php else: ?>
  <div class="es"><div class="ei">🔍</div><h3>No artisans found</h3><p>Try adjusting your search filters</p><br><a href="index.php?browse=1" class="btn btn-p">Clear Search</a></div>
  <?php endif ?>
 </div>
</section>
<?php endif ?>

<?php
// ── ARTISAN CARD FUNCTION ─────────────────────────────────
function artisanCard(array $a): void { ?>
<a href="profile.php?id=<?=$a['profile_id']?>" class="ac" style="text-decoration:none;color:inherit">
 <?php if($a['profile_picture']&&$a['profile_picture']!=='default.png'): ?>
 <img class="ac-img" src="<?=e(imgUrl($a['profile_picture']))?>" alt="<?=e($a['first_name'])?>" loading="lazy">
 <?php else: ?>
 <div class="ac-ph">👤</div>
 <?php endif ?>
 <div class="ac-b">
  <div class="ac-n"><?=e($a['first_name'].' '.$a['last_name'])?></div>
  <div class="ac-t">🛠 <?=e($a['trade_category'])?> · 📍 <?=e($a['state_of_operation'])?></div>
  <?php if($a['lga_of_operation']??''): ?><div style="font-size:.78rem;color:var(--light);margin-bottom:.3rem"><?=e($a['lga_of_operation'])?></div><?php endif ?>
  <div class="ac-m">
   <div><?php if ((int)$a['total_reviews'] > 0): ?>
    <?=stars((float)$a['average_rating'])?> <span style="font-size:.78rem;color:var(--light)">(<?=$a['total_reviews']?>)</span>
   <?php else: ?><span style="font-size:.82rem;color:var(--light)">No reviews yet</span><?php endif ?></div>
   <span class="b b-g">✓ Verified</span>
  </div>
 </div>
</a>
<?php }

foot();
?>
