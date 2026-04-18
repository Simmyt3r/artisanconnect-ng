<?php
require_once 'config.php';
requireLogin();

$myId  = uid();
$role  = $_SESSION['role'];
$csrf  = csrf();

// Fetch base user record
$uStmt = db()->prepare("SELECT * FROM users WHERE user_id=?");
$uStmt->execute([$myId]); $me = $uStmt->fetch();

// Artisan profile
$ap = null;
if (isArtisan()) {
    $apStmt = db()->prepare("SELECT * FROM artisan_profiles WHERE user_id=?");
    $apStmt->execute([$myId]); $ap = $apStmt->fetch();
}

$states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno',
           'Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo',
           'Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa',
           'Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba',
           'Yobe','Zamfara'];
$categories = ['Carpentry','Tailoring','Electrical','Plumbing','Painting','Welding',
                'Masonry','Hairdressing','Barbering','Cobbling','Weaving','Pottery',
                'Blacksmithing','Auto Mechanic','Tiling','Upholstery','Photography','Other'];

// ═══════════════════════════════════════════════════════════
// POST ACTION HANDLERS
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── UPDATE BASIC PROFILE ─────────────────────────────
    if ($action === 'update_profile') {
        $first = trim($_POST['first_name']??'');
        $last  = trim($_POST['last_name']??'');
        $phone = trim($_POST['phone']??'');
        if (!$first||!$last) { flash('error','Name fields are required.'); redirect('dashboard.php'); }

        // Avatar upload
        $avatar = $me['profile_picture'];
        $newAvatar = uploadFile('profile_picture', 'avatar');
        if ($newAvatar) $avatar = $newAvatar;

        db()->prepare("UPDATE users SET first_name=?,last_name=?,phone=?,profile_picture=?,updated_at=NOW() WHERE user_id=?")
            ->execute([$first,$last,$phone,$avatar,$myId]);

        // Artisan extra fields
        if (isArtisan() && $ap) {
            $trade = trim($_POST['trade_category']??'');
            $state = trim($_POST['state_of_operation']??'');
            $lga   = trim($_POST['lga_of_operation']??'');
            $bio   = trim($_POST['bio']??'');
            $yrs   = (int)($_POST['years_experience']??0);
            $avail = isset($_POST['is_available'])?1:0;
            db()->prepare("UPDATE artisan_profiles SET trade_category=?,state_of_operation=?,lga_of_operation=?,bio=?,years_experience=?,is_available=? WHERE user_id=?")
                ->execute([$trade,$state,$lga,$bio,$yrs,$avail,$myId]);
        }

        // Change password?
        $newP = $_POST['new_password']??'';
        $curP = $_POST['current_password']??'';
        if ($newP) {
            if (!password_verify($curP, $me['password_hash'])) { flash('error','Current password is incorrect.'); redirect('dashboard.php'); }
            if (strlen($newP)<6) { flash('error','New password must be at least 6 characters.'); redirect('dashboard.php'); }
            db()->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([password_hash($newP,PASSWORD_BCRYPT),$myId]);
        }

        $_SESSION['first_name'] = $first;
        flash('success','Profile updated successfully.');
        redirect('dashboard.php');
    }

    // ── ADD SERVICE ──────────────────────────────────────
    if ($action === 'add_service' && isArtisan() && $ap) {
        $title = trim($_POST['service_title']??'');
        $desc  = trim($_POST['service_description']??'');
        $type  = in_array($_POST['pricing_type']??'',['fixed','hourly','negotiable'])?$_POST['pricing_type']:'negotiable';
        $price = (float)($_POST['base_price']??0)?:(null);
        if (!$title) { flash('error','Service title is required.'); redirect('dashboard.php?t=services'); }
        db()->prepare("INSERT INTO services (artisan_id,service_title,service_description,pricing_type,base_price) VALUES (?,?,?,?,?)")
            ->execute([$ap['profile_id'],$title,$desc,$type,$price]);
        flash('success','Service added.'); redirect('dashboard.php?t=services');
    }

    // ── DELETE SERVICE ───────────────────────────────────
    if ($action === 'delete_service' && isArtisan() && $ap) {
        $sid = (int)($_POST['service_id']??0);
        db()->prepare("DELETE FROM services WHERE service_id=? AND artisan_id=?")->execute([$sid,$ap['profile_id']]);
        flash('success','Service removed.'); redirect('dashboard.php?t=services');
    }

    // ── UPLOAD PORTFOLIO ITEM ────────────────────────────
    if ($action === 'add_portfolio' && isArtisan() && $ap) {
        $title = trim($_POST['item_title']??'') ?: 'My Work';
        $desc  = trim($_POST['item_description']??'');
        $path  = uploadFile('portfolio_image','portfolio');
        if (!$path) { redirect('dashboard.php?t=portfolio'); }
        db()->prepare("INSERT INTO portfolio_items (artisan_id,item_title,item_description,image_path) VALUES (?,?,?,?)")
            ->execute([$ap['profile_id'],$title,$desc,$path]);
        flash('success','Portfolio item added.'); redirect('dashboard.php?t=portfolio');
    }

    // ── DELETE PORTFOLIO ITEM ────────────────────────────
    if ($action === 'delete_portfolio' && isArtisan() && $ap) {
        $iid = (int)($_POST['item_id']??0);
        $r = db()->prepare("SELECT image_path FROM portfolio_items WHERE item_id=? AND artisan_id=?");
        $r->execute([$iid,$ap['profile_id']]); $it = $r->fetch();
        if ($it) {
            $fpath = UPLOAD_DIR . $it['image_path'];
            if (file_exists($fpath)) unlink($fpath);
            db()->prepare("DELETE FROM portfolio_items WHERE item_id=?")->execute([$iid]);
            flash('success','Item deleted.');
        }
        redirect('dashboard.php?t=portfolio');
    }

    // ── ARTISAN RESPOND TO BOOKING ───────────────────────
    if (in_array($action,['accept_booking','decline_booking','complete_booking']) && isArtisan() && $ap) {
        $bid = (int)($_POST['booking_id']??0);
        $map = ['accept_booking'=>'accepted','decline_booking'=>'declined','complete_booking'=>'completed'];
        db()->prepare("UPDATE bookings SET status=?,updated_at=NOW() WHERE booking_id=? AND artisan_id=?")
            ->execute([$map[$action],$bid,$ap['profile_id']]);
        flash('success','Booking status updated.'); redirect('dashboard.php?t=bookings');
    }

    // ── CUSTOMER CONFIRM COMPLETION ──────────────────────
    if ($action === 'confirm_complete' && isCustomer()) {
        $bid = (int)($_POST['booking_id']??0);
        db()->prepare("UPDATE bookings SET customer_confirmed=1,status='completed',updated_at=NOW() WHERE booking_id=? AND customer_user_id=?")
            ->execute([$bid,$myId]);
        flash('success','Job marked as complete. You can now leave a review.'); redirect('dashboard.php?t=bookings');
    }

    // ── SUBMIT REVIEW ─────────────────────────────────────
    if ($action === 'submit_review' && isCustomer()) {
        $bid      = (int)($_POST['booking_id']??0);
        $overall  = max(1,min(5,(int)($_POST['overall_rating']??0)));
        $quality  = (int)($_POST['quality_rating']??0)?:null;
        $time     = (int)($_POST['timeliness_rating']??0)?:null;
        $comm     = (int)($_POST['communication_rating']??0)?:null;
        $title    = trim($_POST['review_title']??'');
        $body     = trim($_POST['review_body']??'');

        // Verify booking belongs to customer and is complete
        $bk = db()->prepare("SELECT b.*,a.user_id AS artisan_user_id FROM bookings b JOIN artisan_profiles a ON b.artisan_id=a.profile_id WHERE b.booking_id=? AND b.customer_user_id=? AND b.status='completed'");
        $bk->execute([$bid,$myId]); $bkRow = $bk->fetch();
        if (!$bkRow) { flash('error','You can only review completed jobs.'); redirect('dashboard.php?t=bookings'); }

        // Check not already reviewed
        $ex = db()->prepare("SELECT review_id FROM reviews WHERE booking_id=?");
        $ex->execute([$bid]);
        if ($ex->fetch()) { flash('error','You have already reviewed this booking.'); redirect('dashboard.php?t=bookings'); }

        db()->prepare("INSERT INTO reviews (booking_id,customer_user_id,artisan_id,overall_rating,quality_rating,timeliness_rating,communication_rating,review_title,review_body) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$bid,$myId,$bkRow['artisan_id'],$overall,$quality,$time,$comm,$title,$body]);

        // Recalculate artisan average
        $avg = db()->prepare("SELECT AVG(overall_rating) AS avg, COUNT(*) AS cnt FROM reviews WHERE artisan_id=?");
        $avg->execute([$bkRow['artisan_id']]); $avgRow = $avg->fetch();
        db()->prepare("UPDATE artisan_profiles SET average_rating=?,total_reviews=? WHERE profile_id=?")
            ->execute([round($avgRow['avg'],2),$avgRow['cnt'],$bkRow['artisan_id']]);

        flash('success','Review submitted! Thank you.'); redirect('dashboard.php?t=bookings');
    }

    // ── ARTISAN RESPOND TO REVIEW ────────────────────────
    if ($action === 'respond_review' && isArtisan() && $ap) {
        $rid  = (int)($_POST['review_id']??0);
        $resp = trim($_POST['artisan_response']??'');
        db()->prepare("UPDATE reviews SET artisan_response=?,artisan_response_date=NOW() WHERE review_id=? AND artisan_id=?")
            ->execute([$resp,$rid,$ap['profile_id']]);
        flash('success','Response posted.'); redirect('dashboard.php?t=reviews');
    }
}

// ═══════════════════════════════════════════════════════════
// FETCH DATA FOR DISPLAY
// ═══════════════════════════════════════════════════════════
// Re-fetch after any updates
$uStmt->execute([$myId]); $me = $uStmt->fetch();
if (isArtisan()) { $apStmt->execute([$myId]); $ap = $apStmt->fetch(); }

// Bookings
if (isArtisan() && $ap) {
    $bkStmt = db()->prepare("SELECT b.*,u.first_name,u.last_name,u.phone FROM bookings b JOIN users u ON b.customer_user_id=u.user_id WHERE b.artisan_id=? ORDER BY b.created_at DESC");
    $bkStmt->execute([$ap['profile_id']]); $bookings = $bkStmt->fetchAll();
    // My services
    $svStmt = db()->prepare("SELECT * FROM services WHERE artisan_id=? ORDER BY created_at DESC");
    $svStmt->execute([$ap['profile_id']]); $myServices = $svStmt->fetchAll();
    // My portfolio
    $pfStmt = db()->prepare("SELECT * FROM portfolio_items WHERE artisan_id=? ORDER BY display_order,created_at DESC");
    $pfStmt->execute([$ap['profile_id']]); $myPortfolio = $pfStmt->fetchAll();
    // Reviews received
    $rvStmt = db()->prepare("SELECT r.*,u.first_name,u.last_name FROM reviews r JOIN users u ON r.customer_user_id=u.user_id WHERE r.artisan_id=? ORDER BY r.created_at DESC");
    $rvStmt->execute([$ap['profile_id']]); $myReviews = $rvStmt->fetchAll();
} else {
    // Customer bookings
    $bkStmt = db()->prepare("SELECT b.*,ap.trade_category,ap.profile_id AS artisan_pid,u2.first_name AS a_first,u2.last_name AS a_last FROM bookings b JOIN artisan_profiles ap ON b.artisan_id=ap.profile_id JOIN users u2 ON ap.user_id=u2.user_id WHERE b.customer_user_id=? ORDER BY b.created_at DESC");
    $bkStmt->execute([$myId]); $bookings = $bkStmt->fetchAll();
}

$activeTab = $_GET['t'] ?? 'profile';
$statusColors = ['pending'=>'#ed8936','accepted'=>'#48bb78','declined'=>'#e53e3e','in_progress'=>'#4299e1','completed'=>'#38a169','cancelled'=>'#a0aec0'];

head('My Dashboard');
?>

<div class="ph">
 <div class="ctr" style="display:flex;align-items:center;gap:1rem">
  <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0">
   <?php if($me['profile_picture']&&$me['profile_picture']!=='default.png'): ?>
   <img src="<?=e(imgUrl($me['profile_picture']))?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
   <?php else: ?>👤<?php endif ?>
  </div>
  <div>
   <h1 style="font-size:1.5rem;font-weight:800">Welcome, <?=e($me['first_name'])?>!</h1>
   <p style="opacity:.85;font-size:.92rem"><?=ucfirst($role)?> Account <?php if(isArtisan()&&$ap): ?>· <span style="color:var(--gold)"><?=$ap['verification_status']==='verified'?'✓ Verified':'⏳ Pending Verification'?></span><?php endif ?></p>
  </div>
  <?php if(isArtisan()&&$ap): ?><a href="profile.php?id=<?=$ap['profile_id']?>" class="btn" style="margin-left:auto;background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)">View Public Profile →</a><?php endif ?>
 </div>
</div>

<div class="ctr sec" style="padding-top:1.5rem">

 <!-- TABS -->
 <div class="tabs">
  <button class="tab <?=$activeTab==='profile'?'on':''?>" onclick="sw('profile')">👤 Profile</button>
  <?php if(isArtisan()): ?>
  <button class="tab <?=$activeTab==='services'?'on':''?>" onclick="sw('services')">🛠 Services</button>
  <button class="tab <?=$activeTab==='portfolio'?'on':''?>" onclick="sw('portfolio')">🖼 Portfolio</button>
  <button class="tab <?=$activeTab==='reviews'?'on':''?>" onclick="sw('reviews')">⭐ Reviews</button>
  <?php endif ?>
  <button class="tab <?=$activeTab==='bookings'?'on':''?>" onclick="sw('bookings')">📋 Bookings <span style="background:var(--green);color:#fff;border-radius:20px;padding:.05rem .45rem;font-size:.72rem"><?=count($bookings??[])?></span></button>
 </div>

 <!-- ── PROFILE TAB ───────────────────────────────────── -->
 <div id="tp-profile" class="tp <?=$activeTab==='profile'?'on':''?>">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem">
   <div class="card" style="grid-column:span 2">
    <h3 style="font-weight:700;margin-bottom:1rem">Edit Profile</h3>
    <form method="post" enctype="multipart/form-data">
     <input type="hidden" name="_csrf" value="<?=$csrf?>">
     <input type="hidden" name="action" value="update_profile">
     <div class="fr">
      <div class="fg"><label>First Name *</label><input name="first_name" class="fc" value="<?=e($me['first_name'])?>" required></div>
      <div class="fg"><label>Last Name *</label><input name="last_name" class="fc" value="<?=e($me['last_name'])?>" required></div>
     </div>
     <div class="fr">
      <div class="fg"><label>Email</label><input class="fc" value="<?=e($me['email'])?>" disabled style="background:#f0f0f0"></div>
      <div class="fg"><label>Phone</label><input name="phone" class="fc" value="<?=e($me['phone']??'')?>"></div>
     </div>
     <div class="fg"><label>Profile Photo</label><input name="profile_picture" type="file" class="fc" accept="image/*" style="padding:.4rem"></div>

     <?php if(isArtisan()&&$ap): ?>
     <hr style="border:none;border-top:1px solid var(--border);margin:.8rem 0">
     <h4 style="font-weight:700;font-size:.9rem;color:var(--mid);margin-bottom:.8rem;text-transform:uppercase">Artisan Details</h4>
     <div class="fr">
      <div class="fg"><label>Trade Category</label>
       <select name="trade_category" class="fc">
        <?php foreach($categories as $c): ?><option value="<?=e($c)?>" <?=$ap['trade_category']===$c?'selected':''?>><?=e($c)?></option><?php endforeach ?>
       </select>
      </div>
      <div class="fg"><label>Years of Experience</label><input name="years_experience" type="number" min="0" max="60" class="fc" value="<?=(int)$ap['years_experience']?>"></div>
     </div>
     <div class="fr">
      <div class="fg"><label>State of Operation</label>
       <select name="state_of_operation" class="fc">
        <?php foreach($states as $s): ?><option value="<?=e($s)?>" <?=$ap['state_of_operation']===$s?'selected':''?>><?=e($s)?></option><?php endforeach ?>
       </select>
      </div>
      <div class="fg"><label>LGA</label><input name="lga_of_operation" class="fc" value="<?=e($ap['lga_of_operation']??'')?>"></div>
     </div>
     <div class="fg"><label>Bio / Description</label><textarea name="bio" class="fc" rows="4"><?=e($ap['bio']??'')?></textarea></div>
     <div class="fg" style="display:flex;align-items:center;gap:.6rem">
      <input type="checkbox" name="is_available" id="avail" <?=$ap['is_available']?'checked':''?>>
      <label for="avail" style="font-weight:500;color:var(--mid)">Available for new jobs</label>
     </div>
     <?php endif ?>

     <hr style="border:none;border-top:1px solid var(--border);margin:.8rem 0">
     <h4 style="font-weight:700;font-size:.9rem;color:var(--mid);margin-bottom:.8rem;text-transform:uppercase">Change Password <small style="font-weight:400;text-transform:none">(leave blank to keep current)</small></h4>
     <div class="fr">
      <div class="fg"><label>Current Password</label><input name="current_password" type="password" class="fc" placeholder="Enter current password"></div>
      <div class="fg"><label>New Password</label><input name="new_password" type="password" class="fc" placeholder="Min 6 characters" minlength="6"></div>
     </div>
     <button class="btn btn-p" type="submit">Save Changes</button>
    </form>
   </div>
  </div>
 </div>

 <?php if(isArtisan()&&$ap): ?>

 <!-- ── SERVICES TAB ──────────────────────────────────── -->
 <div id="tp-services" class="tp <?=$activeTab==='services'?'on':''?>">
  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.2rem;align-items:start">
   <div>
    <h3 style="font-weight:700;margin-bottom:1rem">My Services (<?=count($myServices)?>)</h3>
    <?php if($myServices): ?>
    <div style="display:flex;flex-direction:column;gap:.7rem">
     <?php foreach($myServices as $sv): ?>
     <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:1rem 1.2rem">
      <div>
       <strong><?=e($sv['service_title'])?></strong>
       <div style="font-size:.82rem;color:var(--light)"><?=e($sv['service_description'])?></div>
       <span class="b b-gr"><?=e(ucfirst($sv['pricing_type']))?></span>
      </div>
      <div style="display:flex;align-items:center;gap:.8rem">
       <?php if($sv['base_price']): ?><strong style="color:var(--green)">₦<?=number_format($sv['base_price'],0)?></strong><?php endif ?>
       <form method="post" onsubmit="return confirm('Delete this service?')">
        <input type="hidden" name="_csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="delete_service">
        <input type="hidden" name="service_id" value="<?=$sv['service_id']?>">
        <button class="btn btn-d btn-sm" type="submit">Delete</button>
       </form>
      </div>
     </div>
     <?php endforeach ?>
    </div>
    <?php else: ?><div class="es"><div class="ei">🛠</div><p>No services yet. Add your first service →</p></div><?php endif ?>
   </div>
   <div class="card">
    <h3 style="font-weight:700;margin-bottom:1rem">Add New Service</h3>
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=$csrf?>">
     <input type="hidden" name="action" value="add_service">
     <div class="fg"><label>Service Title *</label><input name="service_title" class="fc" required placeholder="e.g. Custom Wardrobe"></div>
     <div class="fg"><label>Description</label><textarea name="service_description" class="fc" rows="3" placeholder="What does this service include?"></textarea></div>
     <div class="fg"><label>Pricing Type</label>
      <select name="pricing_type" class="fc" id="ptSel" onchange="document.getElementById('priceField').style.display=this.value!=='negotiable'?'block':'none'">
       <option value="negotiable">Negotiable</option>
       <option value="fixed">Fixed Price</option>
       <option value="hourly">Hourly Rate</option>
      </select>
     </div>
     <div class="fg" id="priceField" style="display:none"><label>Price (₦)</label><input name="base_price" type="number" min="0" step="50" class="fc" placeholder="e.g. 25000"></div>
     <button class="btn btn-p btn-blk" type="submit">Add Service</button>
    </form>
   </div>
  </div>
 </div>

 <!-- ── PORTFOLIO TAB ─────────────────────────────────── -->
 <div id="tp-portfolio" class="tp <?=$activeTab==='portfolio'?'on':''?>">
  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.2rem;align-items:start">
   <div>
    <h3 style="font-weight:700;margin-bottom:1rem">Portfolio Items (<?=count($myPortfolio)?>)</h3>
    <?php if($myPortfolio): ?>
    <div class="pg">
     <?php foreach($myPortfolio as $pi): ?>
     <div class="pi" style="position:relative">
      <img src="<?=e(imgUrl($pi['image_path']))?>" alt="<?=e($pi['item_title'])?>" loading="lazy">
      <div class="pio" style="justify-content:space-between;align-items:flex-end;flex-direction:column">
       <form method="post" onsubmit="return confirm('Delete this item?')">
        <input type="hidden" name="_csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="delete_portfolio">
        <input type="hidden" name="item_id" value="<?=$pi['item_id']?>">
        <button class="btn btn-d btn-sm" type="submit" style="padding:.2rem .6rem;font-size:.75rem">✕</button>
       </form>
       <div><?=e($pi['item_title'])?></div>
      </div>
     </div>
     <?php endforeach ?>
    </div>
    <?php else: ?><div class="es"><div class="ei">🖼</div><p>No portfolio items yet</p></div><?php endif ?>
   </div>
   <div class="card">
    <h3 style="font-weight:700;margin-bottom:1rem">Upload Work</h3>
    <form method="post" enctype="multipart/form-data">
     <input type="hidden" name="_csrf" value="<?=$csrf?>">
     <input type="hidden" name="action" value="add_portfolio">
     <div class="fg"><label>Image * <small style="font-weight:400;color:var(--light)">(JPG/PNG, max <?=MAX_UPLOAD_MB?>MB)</small></label>
      <input name="portfolio_image" type="file" class="fc" accept="image/*" required style="padding:.4rem" onchange="prevImg(event)">
      <img id="prev" style="display:none;margin-top:.6rem;border-radius:8px;max-height:150px;object-fit:cover;width:100%">
     </div>
     <div class="fg"><label>Title</label><input name="item_title" class="fc" placeholder="e.g. Custom Wardrobe — Garki"></div>
     <div class="fg"><label>Description</label><textarea name="item_description" class="fc" rows="2" placeholder="Brief description of this work…"></textarea></div>
     <button class="btn btn-p btn-blk" type="submit">Upload</button>
    </form>
   </div>
  </div>
 </div>

 <!-- ── REVIEWS TAB ───────────────────────────────────── -->
 <div id="tp-reviews" class="tp <?=$activeTab==='reviews'?'on':''?>">
  <h3 style="font-weight:700;margin-bottom:1rem">Reviews Received (<?=count($myReviews)?>)</h3>
  <?php if($myReviews): ?>
  <?php foreach($myReviews as $rv): ?>
  <div class="card" style="margin-bottom:.9rem">
   <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem">
    <strong><?=e($rv['first_name'].' '.$rv['last_name'])?></strong>
    <div><?=stars((float)$rv['overall_rating'])?> <span style="font-size:.78rem;color:var(--light)"><?=ago($rv['created_at'])?></span></div>
   </div>
   <?php if($rv['review_title']): ?><div style="font-weight:600;font-size:.92rem;margin-bottom:.3rem"><?=e($rv['review_title'])?></div><?php endif ?>
   <?php if($rv['review_body']): ?><p style="font-size:.88rem;color:var(--mid)"><?=nl2br(e($rv['review_body']))?></p><?php endif ?>
   <?php if(!$rv['artisan_response']): ?>
   <details style="margin-top:.8rem">
    <summary style="font-size:.85rem;color:var(--green);cursor:pointer;font-weight:600">Reply to this review</summary>
    <form method="post" style="margin-top:.6rem">
     <input type="hidden" name="_csrf" value="<?=$csrf?>">
     <input type="hidden" name="action" value="respond_review">
     <input type="hidden" name="review_id" value="<?=$rv['review_id']?>">
     <textarea name="artisan_response" class="fc" rows="3" placeholder="Your professional response…" required></textarea>
     <button class="btn btn-p btn-sm" type="submit" style="margin-top:.5rem">Post Response</button>
    </form>
   </details>
   <?php else: ?>
   <div style="background:var(--green-light);border-left:3px solid var(--green);padding:.6rem .9rem;border-radius:6px;margin-top:.7rem;font-size:.85rem"><strong style="color:var(--green)">Your response:</strong> <?=e($rv['artisan_response'])?></div>
   <?php endif ?>
  </div>
  <?php endforeach ?>
  <?php else: ?><div class="es"><div class="ei">⭐</div><h3>No reviews yet</h3><p>Complete jobs to receive customer reviews</p></div><?php endif ?>
 </div>

 <?php endif // end isArtisan ?>

 <!-- ── BOOKINGS TAB ──────────────────────────────────── -->
 <div id="tp-bookings" class="tp <?=$activeTab==='bookings'?'on':''?>">
  <h3 style="font-weight:700;margin-bottom:1rem">My Bookings (<?=count($bookings)?>)</h3>
  <?php if($bookings): ?>
  <div class="tw">
  <table>
   <thead>
    <tr>
     <?php if(isArtisan()): ?><th>Customer</th><?php else: ?><th>Artisan</th><?php endif ?>
     <th>Description</th><th>Status</th><th>Date</th><th>Actions</th>
    </tr>
   </thead>
   <tbody>
   <?php foreach($bookings as $bk): ?>
   <tr>
    <?php if(isArtisan()): ?>
    <td><strong><?=e($bk['first_name'].' '.$bk['last_name'])?></strong><?php if($bk['phone']): ?><br><small style="color:var(--light)"><?=e($bk['phone'])?></small><?php endif ?></td>
    <?php else: ?>
    <td><a href="profile.php?id=<?=$bk['artisan_pid']?>"><?=e($bk['a_first'].' '.$bk['a_last'])?></a><br><small style="color:var(--light)"><?=e($bk['trade_category']??'')?></small></td>
    <?php endif ?>
    <td style="max-width:200px"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px" title="<?=e($bk['booking_description'])?>"><?=e(substr($bk['booking_description'],0,60))?><?=strlen($bk['booking_description'])>60?'…':''?></div><?php if($bk['agreed_price']): ?><small style="color:var(--green);font-weight:600">₦<?=number_format($bk['agreed_price'],0)?></small><?php endif ?></td>
    <td><span class="b" style="background:<?=$statusColors[$bk['status']]??'#ccc'?>22;color:<?=$statusColors[$bk['status']]??'#888'?>"><?=ucfirst(str_replace('_',' ',$bk['status']))?></span></td>
    <td style="white-space:nowrap;font-size:.82rem;color:var(--light)"><?=date('d M Y',strtotime($bk['created_at']))?></td>
    <td>
     <?php if(isArtisan()&&$bk['status']==='pending'): ?>
     <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="accept_booking"><input type="hidden" name="booking_id" value="<?=$bk['booking_id']?>"><button class="btn btn-p btn-sm" type="submit">✓ Accept</button></form>
     <form method="post" style="display:inline;margin-left:.3rem"><input type="hidden" name="_csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="decline_booking"><input type="hidden" name="booking_id" value="<?=$bk['booking_id']?>"><button class="btn btn-d btn-sm" type="submit">✗ Decline</button></form>
     <?php elseif(isArtisan()&&$bk['status']==='accepted'): ?>
     <form method="post"><input type="hidden" name="_csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="complete_booking"><input type="hidden" name="booking_id" value="<?=$bk['booking_id']?>"><button class="btn btn-g btn-sm" type="submit">Mark Complete</button></form>
     <?php elseif(isCustomer()&&$bk['status']==='completed'&&!$bk['customer_confirmed']): ?>
     <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="confirm_complete"><input type="hidden" name="booking_id" value="<?=$bk['booking_id']?>"><button class="btn btn-p btn-sm" type="submit">Confirm Complete</button></form>
     <?php elseif(isCustomer()&&$bk['status']==='completed'&&$bk['customer_confirmed']): ?>
     <?php // Check if already reviewed
     $hasRev = db()->prepare("SELECT review_id FROM reviews WHERE booking_id=?");
     $hasRev->execute([$bk['booking_id']]); $reviewed = $hasRev->fetch(); ?>
     <?php if(!$reviewed): ?>
     <button class="btn btn-g btn-sm" onclick="openReview(<?=$bk['booking_id']?>)">Leave Review</button>
     <?php else: ?><span class="b b-g">✓ Reviewed</span><?php endif ?>
     <?php else: ?>—<?php endif ?>
    </td>
   </tr>
   <?php endforeach ?>
   </tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="es"><div class="ei">📋</div><h3>No bookings yet</h3><p><?=isArtisan()?'Bookings from customers will appear here.':'<a href="index.php">Find an artisan</a> to make your first booking.'?></p></div>
  <?php endif ?>
 </div>

</div>

<!-- REVIEW MODAL -->
<div id="revModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;align-items:center;justify-content:center;padding:1rem">
 <div class="card" style="max-width:500px;width:100%;max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
   <h3 style="font-weight:700">Leave a Review ⭐</h3>
   <button onclick="document.getElementById('revModal').style.display='none'" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--light)">✕</button>
  </div>
  <form method="post">
   <input type="hidden" name="_csrf" value="<?=$csrf?>">
   <input type="hidden" name="action" value="submit_review">
   <input type="hidden" name="booking_id" id="revBid" value="">
   <div class="fg">
    <label>Overall Rating *</label>
    <div style="display:flex;gap:.4rem;font-size:1.6rem;cursor:pointer" id="starRow">
     <?php for($i=1;$i<=5;$i++): ?><span onclick="setRating(<?=$i?>)" id="star<?=$i?>" style="color:#ddd;transition:.15s">★</span><?php endfor ?>
    </div>
    <input type="hidden" name="overall_rating" id="overallRating" required>
   </div>
   <div class="fr">
    <div class="fg"><label>Quality (optional)</label><select name="quality_rating" class="fc"><option value="">--</option><?php for($i=5;$i>=1;$i--): ?><option value="<?=$i?>"><?=$i?>★</option><?php endfor ?></select></div>
    <div class="fg"><label>Timeliness</label><select name="timeliness_rating" class="fc"><option value="">--</option><?php for($i=5;$i>=1;$i--): ?><option value="<?=$i?>"><?=$i?>★</option><?php endfor ?></select></div>
   </div>
   <div class="fg"><label>Review Title</label><input name="review_title" class="fc" placeholder="e.g. Excellent work, very professional"></div>
   <div class="fg"><label>Review Body</label><textarea name="review_body" class="fc" rows="4" placeholder="Share details about your experience…"></textarea></div>
   <button class="btn btn-p btn-blk" type="submit">Submit Review</button>
  </form>
 </div>
</div>

<script>
function sw(t){
  document.querySelectorAll('.tp,.tab').forEach(el=>el.classList.remove('on'));
  document.getElementById('tp-'+t).classList.add('on');
  document.querySelectorAll('.tab').forEach(el=>{if(el.textContent.toLowerCase().includes(t.substring(0,4)))el.classList.add('on');});
}
function prevImg(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=ev=>{const i=document.getElementById('prev');i.src=ev.target.result;i.style.display='block';};r.readAsDataURL(f);}
function openReview(bid){document.getElementById('revBid').value=bid;document.getElementById('revModal').style.display='flex';}
function setRating(n){document.getElementById('overallRating').value=n;for(let i=1;i<=5;i++)document.getElementById('star'+i).style.color=i<=n?'var(--gold)':'#ddd';}
document.querySelector('.tabs')&&document.querySelectorAll('.tab').forEach(t=>{t.addEventListener('click',()=>{const id=t.parentElement.nextElementSibling;});});
// Init tab from URL
(function(){const p=new URLSearchParams(location.search);const t=p.get('t');if(t)sw(t);})();
</script>

<?php foot(); ?>
