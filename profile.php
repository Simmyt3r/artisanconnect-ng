<?php
require_once 'config.php';

$pid = (int)($_GET['id'] ?? 0);
if (!$pid) { flash('error','Invalid artisan.'); redirect('index.php'); }

// Fetch artisan
$stmt = db()->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.profile_picture, u.created_at AS joined
    FROM artisan_profiles a JOIN users u ON a.user_id=u.user_id
    WHERE a.profile_id=?
");
$stmt->execute([$pid]);
$a = $stmt->fetch();
if (!$a) { flash('error','Artisan not found.'); redirect('index.php'); }

// Portfolio
$pf = db()->prepare("SELECT * FROM portfolio_items WHERE artisan_id=? ORDER BY display_order, created_at DESC");
$pf->execute([$pid]); $portfolio = $pf->fetchAll();

// Services
$sv = db()->prepare("SELECT * FROM services WHERE artisan_id=? AND is_active=1 ORDER BY created_at");
$sv->execute([$pid]); $services = $sv->fetchAll();

// Reviews
$rv = db()->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r JOIN users u ON r.customer_user_id=u.user_id
    WHERE r.artisan_id=? ORDER BY r.created_at DESC LIMIT 20
");
$rv->execute([$pid]); $reviews = $rv->fetchAll();

// ── HANDLE BOOKING SUBMIT ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='book') {
    requireLogin();
    if (!isCustomer()) { flash('error','Only customers can submit bookings.'); redirect("profile.php?id=$pid"); }
    verifyCsrf();
    $desc   = trim($_POST['booking_description']??'');
    $sid    = (int)($_POST['service_id']??0) ?: null;
    $price  = (float)($_POST['agreed_price']??0) ?: null;
    if (!$desc) { flash('error','Please describe the work you need.'); redirect("profile.php?id=$pid"); }

    $ins = db()->prepare("INSERT INTO bookings (customer_user_id,artisan_id,service_id,booking_description,agreed_price) VALUES (?,?,?,?,?)");
    $ins->execute([uid(),$pid,$sid,$desc,$price]);
    flash('success','Booking request sent! The artisan will respond shortly.');
    redirect("profile.php?id=$pid");
}

// ── HANDLE MESSAGE START ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='start_conv') {
    requireLogin();
    verifyCsrf();
    $artisanUserId = (int)$a['user_id'];
    $myId = uid();
    if ($myId === $artisanUserId) redirect("messages.php");
    // Ensure consistent ordering (smaller id first)
    $u1 = min($myId,$artisanUserId); $u2 = max($myId,$artisanUserId);
    $c = db()->prepare("SELECT conversation_id FROM conversations WHERE user1_id=? AND user2_id=?");
    $c->execute([$u1,$u2]); $conv = $c->fetch();
    if (!$conv) {
        $ic = db()->prepare("INSERT INTO conversations (user1_id,user2_id) VALUES (?,?)");
        $ic->execute([$u1,$u2]);
        $convId = db()->lastInsertId();
    } else { $convId = $conv['conversation_id']; }
    redirect("messages.php?conv=$convId");
}

$fullName = $a['first_name'].' '.$a['last_name'];
$verified = $a['verification_status']==='verified';

head(e($fullName).' — Artisan Profile');
?>

<div class="ph">
 <div class="ctr" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
  <?php if ($a['profile_picture']&&$a['profile_picture']!=='default.png'): ?>
  <img src="<?=e(imgUrl($a['profile_picture']))?>" alt="<?=e($fullName)?>" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.4)">
  <?php else: ?>
  <div style="width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2.5rem;flex-shrink:0">👤</div>
  <?php endif ?>
  <div style="flex:1">
   <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.3rem">
    <h1 style="font-size:1.7rem;font-weight:800"><?=e($fullName)?></h1>
    <?php if($verified): ?><span class="b b-gold" style="font-size:.78rem">✓ Verified</span><?php else: ?><span class="b b-gr" style="font-size:.78rem">Unverified</span><?php endif ?>
   </div>
   <p style="opacity:.88;margin-bottom:.4rem">🛠 <?=e($a['trade_category'])?><?=$a['trade_subcategory']?' — '.e($a['trade_subcategory']):''; ?> · 📍 <?=e($a['lga_of_operation'])?>, <?=e($a['state_of_operation'])?></p>
   <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;opacity:.9">
    <?=stars((float)$a['average_rating'])?> <span style="font-size:.85rem">(<?=e($a['total_reviews'])?> review<?=$a['total_reviews']!=1?'s':''?>)</span>
    · <span style="font-size:.85rem">⏱ <?=$a['years_experience']?> yrs experience</span>
    · <span style="font-size:.85rem">📅 Joined <?=date('M Y',strtotime($a['joined']))?></span>
   </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:.6rem">
   <?php if(isLoggedIn()&&uid()!==$a['user_id']): ?>
   <form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="start_conv"><button class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)">💬 Message</button></form>
   <?php elseif(!isLoggedIn()): ?>
   <a href="auth.php?next=<?=urlencode("profile.php?id=$pid")?>" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)">💬 Message</a>
   <?php endif ?>
   <?php if(!isLoggedIn()||isCustomer()): ?>
   <a href="#book" class="btn btn-g">📋 Book Now</a>
   <?php endif ?>
  </div>
 </div>
</div>

<div class="ctr sec" style="padding-top:1.5rem">
 <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

  <!-- LEFT COLUMN -->
  <div>
   <!-- BIO -->
   <?php if($a['bio']): ?>
   <div class="card" style="margin-bottom:1.2rem">
    <h2 class="card-t" style="font-size:1rem;font-weight:700;color:var(--mid);margin-bottom:.7rem;text-transform:uppercase;font-size:.82rem;letter-spacing:.05em">ABOUT</h2>
    <p style="color:var(--mid);line-height:1.75"><?=nl2br(e($a['bio']))?></p>
   </div>
   <?php endif ?>

   <!-- TABS: Portfolio / Services / Reviews -->
   <div class="tabs">
    <button class="tab on" onclick="sw('portfolio')">🖼 Portfolio (<?=count($portfolio)?>)</button>
    <button class="tab" onclick="sw('services')">🛠 Services (<?=count($services)?>)</button>
    <button class="tab" onclick="sw('reviews')">⭐ Reviews (<?=$a['total_reviews']?>)</button>
   </div>

   <!-- PORTFOLIO -->
   <div id="tp-portfolio" class="tp on">
    <?php if($portfolio): ?>
    <div class="pg">
     <?php foreach($portfolio as $i=>$p): ?>
     <div class="pi" onclick="showLb('<?=e(imgUrl($p['image_path']))?>','<?=e(addslashes($p['item_title']))?>')">
      <img src="<?=e(imgUrl($p['image_path']))?>" alt="<?=e($p['item_title'])?>" loading="lazy">
      <div class="pio"><?=e($p['item_title'])?></div>
     </div>
     <?php endforeach ?>
    </div>
    <?php else: ?><div class="es"><div class="ei">🖼</div><p>No portfolio items yet</p></div><?php endif ?>
   </div>

   <!-- SERVICES -->
   <div id="tp-services" class="tp">
    <?php if($services): ?>
    <div style="display:flex;flex-direction:column;gap:.8rem">
     <?php foreach($services as $sv): ?>
     <div class="card" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <div>
       <div style="font-weight:700;font-size:.97rem;margin-bottom:.25rem"><?=e($sv['service_title'])?></div>
       <?php if($sv['service_description']): ?><p style="font-size:.87rem;color:var(--mid)"><?=e($sv['service_description'])?></p><?php endif ?>
       <span class="b b-gr" style="margin-top:.4rem"><?=e(ucfirst($sv['pricing_type']))?> pricing</span>
      </div>
      <div style="text-align:right;white-space:nowrap">
       <?php if($sv['base_price']): ?><div style="font-size:1.15rem;font-weight:800;color:var(--green)">₦<?=number_format($sv['base_price'],0)?></div><?php else: ?><div style="color:var(--light);font-size:.9rem">Negotiable</div><?php endif ?>
       <a href="#book" class="btn btn-p btn-sm" style="margin-top:.5rem">Book</a>
      </div>
     </div>
     <?php endforeach ?>
    </div>
    <?php else: ?><div class="es"><div class="ei">🛠</div><p>No services listed yet</p></div><?php endif ?>
   </div>

   <!-- REVIEWS -->
   <div id="tp-reviews" class="tp">
    <?php if($reviews): ?>
    <?php foreach($reviews as $rv): ?>
    <div class="card" style="margin-bottom:.9rem">
     <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;margin-bottom:.6rem">
      <div>
       <strong><?=e($rv['first_name'].' '.$rv['last_name'])?></strong>
       <?php if($rv['review_title']): ?><span style="font-size:.88rem;color:var(--mid)"> — <?=e($rv['review_title'])?></span><?php endif ?>
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
       <?=stars((float)$rv['overall_rating'])?>
       <span style="font-size:.78rem;color:var(--light)"><?=ago($rv['created_at'])?></span>
      </div>
     </div>
     <?php if($rv['review_body']): ?><p style="font-size:.9rem;color:var(--mid)"><?=nl2br(e($rv['review_body']))?></p><?php endif ?>
     <?php if($rv['artisan_response']): ?>
     <div style="background:var(--green-light);border-left:3px solid var(--green);padding:.65rem .9rem;border-radius:6px;margin-top:.8rem;font-size:.87rem">
      <strong style="color:var(--green)">Artisan's Response:</strong><br>
      <?=nl2br(e($rv['artisan_response']))?>
     </div>
     <?php endif ?>
    </div>
    <?php endforeach ?>
    <?php else: ?><div class="es"><div class="ei">⭐</div><p>No reviews yet. Be the first to review!</p></div><?php endif ?>
   </div>
  </div>

  <!-- RIGHT SIDEBAR — BOOKING FORM -->
  <div style="position:sticky;top:74px" id="book">
   <div class="card">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">📋 Book <?=e($a['first_name'])?></h2>
    <?php if(isCustomer()): ?>
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=csrf()?>">
     <input type="hidden" name="action" value="book">
     <div class="fg">
      <label>Select Service (optional)</label>
      <select name="service_id" class="fc">
       <option value="">-- Custom / General Request --</option>
       <?php foreach($services as $sv): ?><option value="<?=$sv['service_id']?>"><?=e($sv['service_title'])?><?=$sv['base_price']?' — ₦'.number_format((float)$sv['base_price'],0):''?></option><?php endforeach ?>
      </select>
     </div>
     <div class="fg">
      <label>Describe the Work You Need *</label>
      <textarea name="booking_description" class="fc" rows="4" required placeholder="Describe what you need, materials, timeline, location…"></textarea>
     </div>
     <div class="fg">
      <label>Your Budget (₦) <small style="font-weight:400;color:var(--light)">optional</small></label>
      <input name="agreed_price" type="number" min="0" step="100" class="fc" placeholder="e.g. 25000">
     </div>
     <button class="btn btn-p btn-blk" type="submit">Send Booking Request</button>
    </form>
    <?php elseif(isArtisan()): ?>
    <div class="es"><p style="font-size:.9rem">Artisan accounts cannot book services. <a href="index.php">Browse other artisans.</a></p></div>
    <?php else: ?>
    <div class="es"><p style="font-size:.9rem">Please <a href="auth.php?tab=register">register as a customer</a> or <a href="auth.php">login</a> to book this artisan.</p></div>
    <a href="auth.php?tab=register" class="btn btn-p btn-blk">Register to Book</a>
    <?php endif ?>
   </div>

   <!-- QUICK INFO -->
   <div class="card" style="margin-top:1rem;font-size:.88rem">
    <div style="display:flex;flex-direction:column;gap:.55rem;color:var(--mid)">
     <div>🗓 Member since <?=date('M Y',strtotime($a['joined']))?></div>
     <?php if($a['phone']&&isLoggedIn()): ?><div>📞 <?=e($a['phone'])?></div><?php endif ?>
     <div><?=$a['is_available']?'<span style="color:var(--green)">✅ Available for hire</span>':'<span style="color:#e53e3e">❌ Not currently available</span>'?></div>
     <?php if(count($portfolio)): ?><div>🖼 <?=count($portfolio)?> portfolio item<?=count($portfolio)!=1?'s':''?></div><?php endif ?>
    </div>
   </div>
  </div>

 </div>
</div>

<!-- LIGHTBOX -->
<div id="lb" class="lb" onclick="closeLb(event)">
 <span class="lb-close" onclick="document.getElementById('lb').classList.remove('open')">✕</span>
 <div style="text-align:center">
  <img id="lb-img" src="" alt="">
  <p id="lb-cap" style="color:#fff;margin-top:.7rem;font-size:.9rem"></p>
 </div>
</div>

<script>
function sw(t) {
  document.querySelectorAll('.tp,.tab').forEach(el=>el.classList.remove('on'));
  document.getElementById('tp-'+t).classList.add('on');
  document.querySelectorAll('.tab').forEach(el=>{if(el.textContent.toLowerCase().includes(t.substring(0,4)))el.classList.add('on');});
}
function showLb(src,cap){document.getElementById('lb-img').src=src;document.getElementById('lb-cap').textContent=cap;document.getElementById('lb').classList.add('open');}
function closeLb(e){if(e.target===document.getElementById('lb'))document.getElementById('lb').classList.remove('open');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('lb').classList.remove('open');});
</script>

<?php foot(); ?>
