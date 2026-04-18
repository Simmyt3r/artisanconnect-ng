<?php
require_once 'config.php';
requireLogin();

$myId   = uid();
$convId = (int)($_GET['conv'] ?? 0);
$csrf   = csrf();

// ── SEND MESSAGE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'')==='send') {
    verifyCsrf();
    $cid  = (int)($_POST['conv_id'] ?? 0);
    $body = trim($_POST['message_body'] ?? '');
    if (!$body || !$cid) { redirect("messages.php?conv=$cid"); }

    // Verify user is part of this conversation
    $ck = db()->prepare("SELECT conversation_id FROM conversations WHERE conversation_id=? AND (user1_id=? OR user2_id=?)");
    $ck->execute([$cid,$myId,$myId]);
    if ($ck->fetch()) {
        db()->prepare("INSERT INTO messages (conversation_id,sender_id,message_body) VALUES (?,?,?)")->execute([$cid,$myId,$body]);
        db()->prepare("UPDATE conversations SET last_message_at=NOW() WHERE conversation_id=?")->execute([$cid]);
    }
    redirect("messages.php?conv=$cid");
}

// ── MARK MESSAGES AS READ ─────────────────────────────────────
if ($convId) {
    db()->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id!=?")->execute([$convId,$myId]);
}

// ── FETCH AJAX (new messages check) ──────────────────────────
if (($_GET['poll']??'')==='1' && $convId) {
    $since = (int)($_GET['since'] ?? 0);
    $ms = db()->prepare("SELECT m.*,u.first_name FROM messages m JOIN users u ON m.sender_id=u.user_id WHERE m.conversation_id=? AND m.message_id>? ORDER BY m.sent_at ASC");
    $ms->execute([$convId,$since]);
    header('Content-Type: application/json');
    echo json_encode($ms->fetchAll());
    exit;
}

// ── FETCH ALL CONVERSATIONS ───────────────────────────────────
$convList = db()->prepare("
    SELECT c.*,
      CASE WHEN c.user1_id=? THEN u2.user_id ELSE u1.user_id END AS other_id,
      CASE WHEN c.user1_id=? THEN u2.first_name ELSE u1.first_name END AS other_first,
      CASE WHEN c.user1_id=? THEN u2.last_name ELSE u1.last_name END AS other_last,
      CASE WHEN c.user1_id=? THEN u2.profile_picture ELSE u1.profile_picture END AS other_pic,
      (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.conversation_id AND m.is_read=0 AND m.sender_id!=?) AS unread_cnt,
      (SELECT m2.message_body FROM messages m2 WHERE m2.conversation_id=c.conversation_id ORDER BY m2.sent_at DESC LIMIT 1) AS last_msg
    FROM conversations c
    JOIN users u1 ON c.user1_id=u1.user_id
    JOIN users u2 ON c.user2_id=u2.user_id
    WHERE c.user1_id=? OR c.user2_id=?
    ORDER BY c.last_message_at DESC
");
$convList->execute([$myId,$myId,$myId,$myId,$myId,$myId,$myId]);
$conversations = $convList->fetchAll();

// ── FETCH MESSAGES FOR ACTIVE CONVERSATION ─────────────────
$messages = [];
$otherUser = null;
if ($convId) {
    $ck2 = db()->prepare("SELECT c.*,u1.first_name AS f1,u1.last_name AS l1,u1.profile_picture AS p1,u1.user_id AS uid1,u2.first_name AS f2,u2.last_name AS l2,u2.profile_picture AS p2,u2.user_id AS uid2 FROM conversations c JOIN users u1 ON c.user1_id=u1.user_id JOIN users u2 ON c.user2_id=u2.user_id WHERE c.conversation_id=? AND (c.user1_id=? OR c.user2_id=?)");
    $ck2->execute([$convId,$myId,$myId]);
    $conv = $ck2->fetch();
    if (!$conv) { flash('error','Conversation not found.'); redirect('messages.php'); }

    if ($conv['uid1'] === $myId) {
        $otherUser = ['user_id'=>$conv['uid2'],'first_name'=>$conv['f2'],'last_name'=>$conv['l2'],'profile_picture'=>$conv['p2']];
    } else {
        $otherUser = ['user_id'=>$conv['uid1'],'first_name'=>$conv['f1'],'last_name'=>$conv['l1'],'profile_picture'=>$conv['p1']];
    }

    $ms = db()->prepare("SELECT m.*,u.first_name FROM messages m JOIN users u ON m.sender_id=u.user_id WHERE m.conversation_id=? ORDER BY m.sent_at ASC");
    $ms->execute([$convId]); $messages = $ms->fetchAll();
}

$lastMsgId = $messages ? end($messages)['message_id'] : 0;

head('Messages', '<style>
.msg-wrap{display:grid;grid-template-columns:270px 1fr;height:calc(100vh - 130px);background:var(--white);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.conv-list{border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column}
.conv-item{padding:.85rem 1rem;border-bottom:1px solid var(--border);cursor:pointer;transition:.15s;text-decoration:none;display:block;color:inherit}
.conv-item:hover,.conv-item.on{background:var(--green-light)}
.msg-panel{display:flex;flex-direction:column;height:100%}
.msg-head{padding:.85rem 1.1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.8rem;background:var(--bg)}
.msg-body{flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.55rem}
.msg-foot{padding:.75rem 1rem;border-top:1px solid var(--border);display:flex;gap:.6rem}
.bbl{max-width:72%;padding:.62rem .95rem;border-radius:16px;font-size:.9rem;line-height:1.5}
.bbl-m{background:var(--green);color:#fff;border-bottom-right-radius:4px;margin-left:auto}
.bbl-t{background:#f0f4f8;color:var(--dark);border-bottom-left-radius:4px}
.bbl-wrap{display:flex;flex-direction:column}
.bbl-ts{font-size:.7rem;color:var(--light);margin-top:.15rem}
.ts-r{text-align:right}.ts-l{text-align:left}
@media(max-width:650px){.msg-wrap{grid-template-columns:1fr;height:auto}.conv-list{max-height:180px;flex-direction:row;overflow-x:auto;overflow-y:hidden;border-right:none;border-bottom:1px solid var(--border)}.conv-item{min-width:120px;border-right:1px solid var(--border);border-bottom:none}}
</style>');
?>

<div class="ctr" style="padding:1.2rem 1rem">
 <div class="msg-wrap">
  <!-- CONVERSATION LIST -->
  <div class="conv-list">
   <div style="padding:.8rem 1rem;border-bottom:1px solid var(--border);font-weight:700;font-size:.88rem;color:var(--mid);text-transform:uppercase;letter-spacing:.05em;background:var(--bg)">💬 Conversations</div>
   <?php if($conversations): ?>
   <?php foreach($conversations as $cv): ?>
   <a href="messages.php?conv=<?=$cv['conversation_id']?>" class="conv-item <?=$cv['conversation_id']===$convId?'on':''?>">
    <div style="display:flex;align-items:center;gap:.6rem">
     <div style="width:36px;height:36px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0">
      <?php if($cv['other_pic']&&$cv['other_pic']!=='default.png'): ?>
      <img src="<?=e(imgUrl($cv['other_pic']))?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
      <?php else: ?>👤<?php endif ?>
     </div>
     <div style="flex:1;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:center">
       <strong style="font-size:.88rem"><?=e($cv['other_first'].' '.$cv['other_last'])?></strong>
       <?php if($cv['unread_cnt']>0): ?><span style="background:var(--green);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.68rem;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0"><?=$cv['unread_cnt']?></span><?php endif ?>
      </div>
      <?php if($cv['last_msg']): ?><div style="font-size:.78rem;color:var(--light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e(substr($cv['last_msg'],0,30))?><?=strlen($cv['last_msg'])>30?'…':''?></div><?php endif ?>
     </div>
    </div>
   </a>
   <?php endforeach ?>
   <?php else: ?>
   <div class="es" style="padding:2rem 1rem"><div class="ei">💬</div><p style="font-size:.85rem">No conversations yet.<br>Visit an artisan's profile and tap Message to start.</p></div>
   <?php endif ?>
  </div>

  <!-- MESSAGE PANEL -->
  <div class="msg-panel">
   <?php if($convId && $otherUser): ?>
   <div class="msg-head">
    <div style="width:38px;height:38px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-size:1.1rem">
     <?php if($otherUser['profile_picture']&&$otherUser['profile_picture']!=='default.png'): ?>
     <img src="<?=e(imgUrl($otherUser['profile_picture']))?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover">
     <?php else: ?>👤<?php endif ?>
    </div>
    <div>
     <strong><?=e($otherUser['first_name'].' '.$otherUser['last_name'])?></strong>
    </div>
   </div>

   <div class="msg-body" id="msgBody">
    <?php if($messages): ?>
    <?php foreach($messages as $msg): ?>
    <?php $mine = $msg['sender_id']==$myId; ?>
    <div class="bbl-wrap" data-id="<?=$msg['message_id']?>">
     <div class="bbl <?=$mine?'bbl-m':'bbl-t'?>"><?=nl2br(e($msg['message_body']))?></div>
     <div class="bbl-ts <?=$mine?'ts-r':'ts-l'?>"><?=e($msg['first_name'])?> · <?=date('d M, H:i',strtotime($msg['sent_at']))?></div>
    </div>
    <?php endforeach ?>
    <?php else: ?>
    <div class="es" style="margin:auto"><div class="ei">💬</div><p>No messages yet. Say hello!</p></div>
    <?php endif ?>
   </div>

   <div class="msg-foot">
    <form method="post" id="msgForm" style="display:flex;gap:.6rem;flex:1">
     <input type="hidden" name="_csrf" value="<?=$csrf?>">
     <input type="hidden" name="action" value="send">
     <input type="hidden" name="conv_id" value="<?=$convId?>">
     <input type="text" name="message_body" id="msgInp" class="fc" placeholder="Type a message…" required autocomplete="off" style="flex:1">
     <button class="btn btn-p" type="submit">Send →</button>
    </form>
   </div>
   <?php else: ?>
   <div class="es" style="margin:auto">
    <div class="ei">💬</div>
    <h3>Select a conversation</h3>
    <p>Choose a conversation from the list, or visit an artisan's profile to start messaging.</p>
    <br><a href="index.php" class="btn btn-p">Browse Artisans</a>
   </div>
   <?php endif ?>
  </div>
 </div>
</div>

<script>
// Auto-scroll to bottom
(function(){const b=document.getElementById('msgBody');if(b)b.scrollTop=b.scrollHeight;})();

// Submit with Enter key
document.getElementById('msgInp')?.addEventListener('keydown',function(e){
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('msgForm').submit();}
});

// Poll for new messages every 5 seconds
<?php if($convId): ?>
let lastId = <?=$lastMsgId?>;
const myId  = <?=$myId?>;
function poll(){
  fetch('messages.php?conv=<?=$convId?>&poll=1&since='+lastId)
    .then(r=>r.json()).then(msgs=>{
      if(!msgs.length)return;
      const body=document.getElementById('msgBody');
      msgs.forEach(m=>{
        if(document.querySelector('[data-id="'+m.message_id+'"]'))return;
        const mine = parseInt(m.sender_id)===myId;
        const wrap=document.createElement('div');wrap.className='bbl-wrap';wrap.dataset.id=m.message_id;
        wrap.innerHTML='<div class="bbl '+(mine?'bbl-m':'bbl-t')+'">'+escH(m.message_body)+'</div><div class="bbl-ts '+(mine?'ts-r':'ts-l')+'">'+escH(m.first_name)+' · '+escH(new Date(m.sent_at.replace(' ','T')).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}))+'</div>';
        body.appendChild(wrap);lastId=Math.max(lastId,parseInt(m.message_id));
      });
      body.scrollTop=body.scrollHeight;
    }).catch(()=>{});
}
function escH(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
setInterval(poll,5000);
<?php endif ?>
</script>

<?php foot(); ?>
