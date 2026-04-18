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
$trade  = trim($_GET['trade']  ?? '');
$state  = trim($_GET['state']  ?? '');
$q      = trim($_GET['q']      ?? '');
$minRat = (float)($_GET['rating'] ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

$where = ['a.verification_status = ?', 'a.is_available = 1'];
$params = ['verified', 1];

if ($trade) { $where[] = 'a.trade_category = ?'; $params[] = $trade; }
if ($state) { $where[] = 'a.state_of_operation = ?'; $params[] = $state; }
if ($q)     { $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.bio LIKE ? OR a.trade_category LIKE ?)";
               $params = array_merge($params, ["%$q%","%$q%","%$q%","%$q%"]); }
if ($minRat) { $where[] = 'a.average_rating >= ?'; $params[] = $minRat; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Total count
$cntStmt = db()->prepare("SELECT COUNT(*) FROM artisan_profiles a JOIN users u ON a.user_id=u.user_id $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch page
$params[] = $perPage; $params[] = $offset;
$stmt = db()->prepare("
    SELECT a.profile_id, a.trade_category, a.state_of_operation, a.lga_of_operation,
           a.average_rating, a.total_reviews, a.verification_status,
           u.first_name, u.last_name, u.profile_picture
    FROM artisan_profiles a
    JOIN users u ON a.user_id = u.user_id
    $whereSQL
    ORDER BY a.average_rating DESC, a.total_reviews DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$artisans = $stmt->fetchAll();

// Featured (homepage — no filters active)
$featured = [];
if (!$trade && !$state && !$q && !$minRat) {
    $fs = db()->query("SELECT a.profile_id, a.trade_category, a.state_of_operation, a.average_rating, a.total_reviews, u.first_name, u.last_name, u.profile_picture FROM artisan_profiles a JOIN users u ON a.user_id=u.user_id WHERE a.verification_status='verified' ORDER BY a.average_rating DESC LIMIT 6");
    $featured = $fs->fetchAll();
}

$searching = ($trade || $state || $q || $minRat);

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
   <input name="q" class="fc" placeholder="Search by name or skill…" value="<?=e($q)?>">
   <select name="trade" class="fc">
    <option value="">All Trades</option>
    <?php foreach($categories as $c): ?>
    <option value="<?=e($c)?>" <?=$trade===$c?'selected':''?>><?=e($c)?></option>
    <?php endforeach ?>
   </select>
   <button class="btn btn-g" type="submit">🔍 Search</button>
   <select name="state" class="fc">
    <option value="">All States</option>
    <?php foreach($states as $s): ?>
    <option value="<?=e($s)?>" <?=$state===$s?'selected':''?>><?=e($s)?></option>
    <?php endforeach ?>
   </select>
   <select name="rating" class="fc">
    <option value="">Any Rating</option>
    <option value="4" <?=$minRat>=4?'selected':''?>>4★ & above</option>
    <option value="3" <?=($minRat>=3&&$minRat<4)?'selected':''?>>3★ & above</option>
   </select>
   <div></div>
  </form>
 </div>
</section>

<!-- STATS BAR ───────────────────────────────────────────── -->
<div style="background:var(--white);border-bottom:1px solid var(--border);padding:.8rem 0">
 <div class="ctr" style="display:flex;gap:2rem;justify-content:center;flex-wrap:wrap;text-align:center">
  <?php
  $totA = db()->query("SELECT COUNT(*) FROM artisan_profiles WHERE verification_status='verified'")->fetchColumn();
  $totT = db()->query("SELECT COUNT(DISTINCT trade_category) FROM artisan_profiles WHERE verification_status='verified'")->fetchColumn();
  $totB = db()->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
  ?>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totA)?></div><div style="font-size:.8rem;color:var(--light)">Verified Artisans</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totT)?></div><div style="font-size:.8rem;color:var(--light)">Trade Categories</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)"><?=number_format($totB)?></div><div style="font-size:.8rem;color:var(--light)">Jobs Completed</div></div>
  <div><div style="font-size:1.5rem;font-weight:800;color:var(--green)">37</div><div style="font-size:.8rem;color:var(--light)">States &amp; FCT</div></div>
 </div>
</div>

<!-- FEATURED ───────────────────────────────────────────── -->
<?php if ($featured): ?>
<section class="sec">
 <div class="ctr">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.5rem">
   <div>
    <h2 style="font-size:1.4rem;font-weight:800">⭐ Top-Rated Artisans</h2>
    <p style="color:var(--light);font-size:.9rem">Verified professionals with the highest customer ratings</p>
   </div>
   <a href="index.php?rating=3" class="btn btn-o btn-sm">View All →</a>
  </div>
  <div class="g3">
   <?php foreach($featured as $a): artisanCard($a); endforeach ?>
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
  <h1>🔍 Search Results</h1>
  <p><?=$total?> artisan<?=$total!=1?'s':''?> found<?=$q?' for "'.e($q).'"':''?><?=$trade?' in '.e($trade):''?><?=$state?' · '.e($state):''?></p>
 </div>
</div>

<section class="sec">
 <div class="ctr">
  <!-- FILTER BAR -->
  <form method="get" style="background:var(--white);border-radius:var(--r);padding:1rem 1.2rem;box-shadow:var(--sh);margin-bottom:1.5rem;display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end">
   <div class="fg" style="flex:1;min-width:150px;margin:0">
    <label style="font-size:.8rem">Keyword</label>
    <input name="q" class="fc" placeholder="Name or skill…" value="<?=e($q)?>">
   </div>
   <div class="fg" style="flex:1;min-width:140px;margin:0">
    <label style="font-size:.8rem">Trade</label>
    <select name="trade" class="fc">
     <option value="">All</option>
     <?php foreach($categories as $c): ?><option value="<?=e($c)?>" <?=$trade===$c?'selected':''?>><?=e($c)?></option><?php endforeach ?>
    </select>
   </div>
   <div class="fg" style="flex:1;min-width:130px;margin:0">
    <label style="font-size:.8rem">State</label>
    <select name="state" class="fc">
     <option value="">All</option>
     <?php foreach($states as $s): ?><option value="<?=e($s)?>" <?=$state===$s?'selected':''?>><?=e($s)?></option><?php endforeach ?>
    </select>
   </div>
   <div class="fg" style="min-width:130px;margin:0">
    <label style="font-size:.8rem">Min Rating</label>
    <select name="rating" class="fc">
     <option value="">Any</option>
     <option value="4" <?=$minRat>=4?'selected':''?>>4★+</option>
     <option value="3" <?=($minRat>=3&&$minRat<4)?'selected':''?>>3★+</option>
    </select>
   </div>
   <button class="btn btn-p" type="submit">Apply</button>
   <a href="index.php" class="btn btn-o">Clear</a>
  </form>

  <?php if ($artisans): ?>
  <div class="g3">
   <?php foreach($artisans as $a): artisanCard($a); endforeach ?>
  </div>
  <!-- PAGINATION -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:.4rem;margin-top:2rem;flex-wrap:wrap">
   <?php
   $qs = http_build_query(array_filter(['q'=>$q,'trade'=>$trade,'state'=>$state,'rating'=>$minRat?:""]));
   for ($p=1; $p<=$totalPages; $p++): ?>
   <a href="?<?=$qs?>&page=<?=$p?>" class="btn <?=$p===$page?'btn-p':'btn-o'?> btn-sm"><?=$p?></a>
   <?php endfor ?>
  </div>
  <?php endif ?>
  <?php else: ?>
  <div class="es"><div class="ei">🔍</div><h3>No artisans found</h3><p>Try adjusting your search filters</p><br><a href="index.php" class="btn btn-p">Clear Search</a></div>
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
   <div><?=stars((float)$a['average_rating'])?> <span style="font-size:.78rem;color:var(--light)">(<?=$a['total_reviews']?>)</span></div>
   <span class="b b-g">✓ Verified</span>
  </div>
 </div>
</a>
<?php }

foot();
?>
