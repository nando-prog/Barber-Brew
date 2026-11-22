const API = {
  catalog: 'public/api/catalog.php', barbers: 'public/api/barbers.php',
  login: 'public/auth/login.php', register: 'public/auth/register.php', logout: 'public/auth/logout.php',
  createOrder: 'public/api/create_order.php', myOrders: 'public/api/my_orders.php',
  barberBookings: 'public/api/barber_bookings.php', barberUpdate: 'public/api/barber_update_booking.php',
  confirmPayment: 'public/api/admin_confirm_payment.php', adminOrders: 'public/api/admin_orders.php'
};
let csrfToken = '';
const rupiah = n => new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(n||0));
const escapeHtml = s => String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function api(url, options={}) {
  const headers = {'Accept':'application/json', ...(options.body?{'Content-Type':'application/json'}:{}), ...(options.method==='POST'?{'X-CSRF-Token':csrfToken}:{}), ...(options.headers||{})};
  const res = await fetch(url,{credentials:'same-origin',...options,headers});
  let data; try{data=await res.json()}catch{throw new Error('Server tidak mengirim respons JSON yang valid.')}
  if(res.status===401 && !url.includes('auth/login')){
    if(!location.pathname.endsWith('login.html')) location.href='login.html?sesi=habis';
    throw new Error('Sesi berakhir. Silakan masuk kembali.');
  }
  if(!res.ok || data.ok===false) throw new Error(data.message||`Permintaan gagal (${res.status})`);
  return data;
}
async function requireAuth(role){
  const d=await api('public/auth/me.php');
  csrfToken=d.csrf_token;
  if(d.user.role!==role){redirectByRole(d.user.role);throw new Error('Halaman ini bukan untuk role Anda.');}
  return d.user;
}
async function getCsrf(){const d=await api('public/api/csrf_token.php');csrfToken=d.csrf_token;return csrfToken;}
function showMessage(el,msg,type=''){if(!el)return;el.textContent=msg;el.className='notice'+(type?' '+type:'');el.hidden=false;}
function hideMessage(el){if(el)el.hidden=true;}
function redirectByRole(role){location.href=role==='barber'?'barber.html':role==='admin'?'admin.html':'customer.html';}
async function initAuth(){
  const form=document.querySelector('#auth-form');
  if(!form)return;

  const mode=document.querySelector('#auth-mode');
  const msg=document.querySelector('#auth-message');
  const submit=document.querySelector('#auth-submit');
  const switchLink=document.querySelector('#switch-mode');
  const password=document.querySelector('#password');

  function setMode(register){
    mode.value=register?'register':'login';
    document.querySelectorAll('[data-register-only]').forEach(x=>{
      x.hidden=!register;
      const input=x.querySelector('input');
      if(input) input.required=register;
    });
    document.querySelector('#auth-title').textContent=register?'Buat Akun':'Selamat Datang';
    document.querySelector('#auth-subtitle').textContent=register
      ?'Daftar sebagai pelanggan Barber & Brew.'
      :'Masuk untuk melanjutkan pesanan dan reservasi.';
    document.querySelector('#switch-copy').textContent=register?'Sudah punya akun?':'Belum punya akun?';
    switchLink.textContent=register?'Masuk sekarang':'Daftar sekarang';
    submit.textContent=register?'Daftar':'Masuk';
    password.autocomplete=register?'new-password':'current-password';
    password.placeholder=register?'Minimal 8 karakter':'Masukkan password';
    hideMessage(msg);
  }

  // Pasang event tombol ganti mode SEBELUM mengambil CSRF.
  // Jadi link Daftar tetap dapat diklik walaupun endpoint CSRF sedang bermasalah.
  switchLink?.addEventListener('click',e=>{
    e.preventDefault();
    setMode(mode.value!=='register');
  });

  const qs=new URLSearchParams(location.search);
  if(qs.get('daftar')==='1') setMode(true);
  if(qs.get('sesi')==='habis') showMessage(msg,'Sesi Anda berakhir atau belum masuk. Silakan login terlebih dahulu.','error');

  // Ambil token secara terpisah; bila gagal, tampilkan pesan tetapi jangan
  // menonaktifkan pergantian tampilan login/registrasi.
  try{
    await getCsrf();
  }catch(err){
    showMessage(msg,`Tidak dapat menghubungi server keamanan: ${err.message}`,'error');
  }

  form.addEventListener('submit',async e=>{
    e.preventDefault();
    hideMessage(msg);
    submit.disabled=true;
    try{
      if(!csrfToken) await getCsrf();
      const fd=new FormData(form);
      const payload={
        email:String(fd.get('email')||'').trim(),
        password:String(fd.get('password')||'')
      };
      const registering=mode.value==='register';
      const endpoint=registering?API.register:API.login;
      if(registering){
        payload.name=String(fd.get('name')||'').trim();
        payload.phone=String(fd.get('phone')||'').trim();
      }
      const data=await api(endpoint,{method:'POST',body:JSON.stringify(payload)});
      if(registering){
        showMessage(msg,data.message||'Registrasi berhasil. Silakan login.','success');
        form.reset();
        setMode(false);
        return;
      }
      csrfToken=data.csrf_token;
      showMessage(msg,data.message,'success');
      redirectByRole(data.user.role);
    }catch(err){
      showMessage(msg,err.message,'error');
    }finally{
      submit.disabled=false;
    }
  });
}
async function initHome(){const root=document.querySelector('#home-services');if(!root)return;try{const d=await api(API.catalog);root.innerHTML=d.services.slice(0,3).map(s=>`<article class="panel p-6"><div class="text-3xl mb-4">✂️</div><h3 class="display text-xl font-bold">${escapeHtml(s.name)}</h3><p class="text-stone-500 text-sm my-3">${escapeHtml(s.description||'Layanan barber profesional.')}</p><div class="flex justify-between items-center"><b class="brand price">${rupiah(s.price)}</b><span class="text-xs text-stone-500">${s.duration_minutes} menit</span></div></article>`).join('');}catch(e){root.innerHTML=`<p class="notice error">${escapeHtml(e.message)}</p>`}}
async function initCustomer(){const form=document.querySelector('#booking-form');if(!form)return;const msg=document.querySelector('#customer-message');try{await requireAuth('customer');const [cat,b]=await Promise.all([api(API.catalog),api(API.barbers)]);
  document.querySelector('#service-id').innerHTML='<option value="">Pilih layanan (opsional jika hanya pesan café)</option>'+cat.services.map(x=>`<option value="${x.id}">${escapeHtml(x.name)} — ${rupiah(x.price)} (${x.duration_minutes} menit)</option>`).join('');
  document.querySelector('#barber-id').innerHTML='<option value="">Pilih barber</option>'+b.barbers.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}${x.experience_years?` — ${x.experience_years} th pengalaman`:''}</option>`).join('');
  document.querySelector('#products').innerHTML=cat.cafe_products.map(p=>`<label class="product-card flex items-center gap-3"><input type="checkbox" name="product" value="${p.id}" data-price="${p.price}"><span class="flex-1"><b class="block">${escapeHtml(p.name)}</b><small class="text-stone-500">${escapeHtml(p.category||'Café')} · Stok ${p.stock}</small><b class="brand text-sm block mt-1">${rupiah(p.price)}</b></span><input class="field w-20" type="number" min="1" max="50" value="1" aria-label="Jumlah ${escapeHtml(p.name)}" data-qty="${p.id}"></label>`).join('')||'<p class="text-stone-500">Belum ada produk tersedia.</p>';
  document.querySelector('#booking-date').min=new Date().toLocaleDateString('en-CA');
 }catch(e){showMessage(msg,e.message,'error');}
 form.addEventListener('submit',async e=>{e.preventDefault();hideMessage(msg);const service=document.querySelector('#service-id').value,barber=document.querySelector('#barber-id').value;const items=[...document.querySelectorAll('input[name="product"]:checked')].map(x=>({product_id:Number(x.value),quantity:Number(document.querySelector(`[data-qty="${x.value}"]`).value)}));const payload={items};if(service){payload.service_id=Number(service);payload.barber_id=Number(barber);payload.booking_date=document.querySelector('#booking-date').value;payload.start_time=document.querySelector('#booking-time').value;payload.note=document.querySelector('#booking-note').value;}try{const d=await api(API.createOrder,{method:'POST',body:JSON.stringify(payload)});showMessage(msg,`${d.message} Kode pesanan: ${d.order.code}. Total: ${rupiah(d.order.total)}. Silakan menunggu konfirmasi pembayaran admin.`,'success');form.reset();await loadMyOrders();}catch(err){showMessage(msg,err.message,'error')}});
 await loadMyOrders();
}
async function loadMyOrders(){const target=document.querySelector('#orders-list');if(!target)return;try{const d=await api(API.myOrders);if(!d.orders.length){target.innerHTML='<p class="text-stone-500">Belum ada pesanan.</p>';return;}target.innerHTML=d.orders.map(o=>`<article class="panel p-5"><div class="flex flex-wrap justify-between gap-2"><b>${escapeHtml(o.order_code)}</b><span class="text-sm text-stone-500">${escapeHtml(o.status)} · Pembayaran: ${escapeHtml(o.payment_status)}</span></div><p class="font-bold brand mt-2">${rupiah(o.total)}</p>${o.booking?`<p class="text-sm mt-2">Reservasi: ${escapeHtml(o.booking.date)} pukul ${escapeHtml(o.booking.start_time)} · ${escapeHtml(o.booking.barber_name)} · ${escapeHtml(o.booking.status)}</p>`:''}<ul class="text-sm text-stone-600 mt-2">${o.items.map(i=>`<li>${escapeHtml(i.name)} × ${i.quantity} — ${rupiah(i.line_total)}</li>`).join('')}</ul></article>`).join('');}catch(e){target.innerHTML=`<p class="notice error">${escapeHtml(e.message)}</p>`}}
async function initBarber(){const target=document.querySelector('#barber-bookings');if(!target)return;const msg=document.querySelector('#barber-message');try{await requireAuth('barber');const d=await api(API.barberBookings);csrfToken=d.csrf_token;if(!d.bookings.length){target.innerHTML='<p class="text-stone-500">Belum ada reservasi.</p>';return;}target.innerHTML=d.bookings.map(b=>`<article class="panel p-5"><div class="flex flex-wrap justify-between gap-2"><b>${escapeHtml(b.customer_name)} · ${escapeHtml(b.service_name)}</b><span class="text-sm">${escapeHtml(b.booking_status)}</span></div><p class="text-sm text-stone-500 mt-2">${escapeHtml(b.booking_date)} · ${escapeHtml(b.start_time)}–${escapeHtml(b.end_time)} · ${rupiah(b.service_price_snapshot)}</p><p class="text-sm mt-1">Pembayaran: ${escapeHtml(b.payment_status||'-')} · Pesanan: ${escapeHtml(b.order_status||'-')}</p><p class="text-sm mt-1">Catatan: ${escapeHtml(b.customer_note||'-')}</p>${b.booking_status==='confirmed'?`<button class="btn btn-dark mt-3" data-update="${b.id}" data-status="in_progress">Mulai pengerjaan</button>`:b.booking_status==='in_progress'?`<button class="btn btn-gold mt-3" data-update="${b.id}" data-status="completed">Tandai selesai</button>`:''}</article>`).join('');target.querySelectorAll('[data-update]').forEach(btn=>btn.addEventListener('click',async()=>{btn.disabled=true;try{await api(API.barberUpdate,{method:'POST',body:JSON.stringify({booking_id:Number(btn.dataset.update),status:btn.dataset.status})});showMessage(msg,'Status reservasi berhasil diperbarui.','success');await initBarber();}catch(e){showMessage(msg,e.message,'error');btn.disabled=false;}}));}catch(e){target.innerHTML=`<p class="notice error">${escapeHtml(e.message)}</p>`}}
const STATUS_ID={awaiting_payment:'Menunggu pembayaran',paid:'Dibayar',processing:'Diproses',completed:'Selesai',cancelled:'Dibatalkan',pending:'Menunggu',failed:'Gagal',refunded:'Dikembalikan',confirmed:'Terkonfirmasi',in_progress:'Dikerjakan'};
const statusId=v=>STATUS_ID[v]||v||'-';
async function initAdmin(){
  const list=document.querySelector('#admin-orders');
  if(!list)return;
  const msg=document.querySelector('#admin-message');
  const form=document.querySelector('#payment-form');
  const search=document.querySelector('#order-search');
  const filter=document.querySelector('#order-filter');
  let orders=[];
  try{await requireAuth('admin');}catch(e){showMessage(msg,e.message,'error');return;}

  function card(o){
    const pending=o.payment_status==='pending'&&o.status==='awaiting_payment';
    const booking=o.booking_date?`<p class="text-sm mt-2">Reservasi: ${escapeHtml(o.booking_date)} pukul ${escapeHtml(String(o.start_time).slice(0,5))}–${escapeHtml(String(o.end_time).slice(0,5))} · ${escapeHtml(o.barber_name||'-')}</p>`:'';
    const items=o.items.map(i=>`<li>${escapeHtml(i.name)} × ${i.quantity} — ${rupiah(i.line_total)}</li>`).join('');
    return `<article class="panel p-5"><div class="flex flex-wrap justify-between gap-2"><b>${escapeHtml(o.order_code)} <span class="text-stone-400 font-normal text-xs">(ID ${o.id})</span></b><span class="text-sm text-stone-500">${escapeHtml(statusId(o.status))} · Pembayaran: ${escapeHtml(statusId(o.payment_status))}</span></div><p class="text-sm text-stone-600 mt-1">${escapeHtml(o.customer_name)} · ${escapeHtml(o.customer_phone||'-')} · ${escapeHtml(o.created_at||'')}</p><p class="font-bold brand mt-2">${rupiah(o.total)}</p>${booking}<ul class="text-sm text-stone-600 mt-2">${items}</ul>${pending?`<button class="btn btn-dark mt-3" data-confirm="${o.id}" data-code="${escapeHtml(o.order_code)}">Konfirmasi pembayaran</button>`:''}</article>`;
  }
  function render(){
    const q=search.value.trim().toLowerCase();
    const onlyPending=filter.value==='pending';
    const rows=orders.filter(o=>(!onlyPending||o.payment_status==='pending')&&(!q||o.order_code.toLowerCase().includes(q)||String(o.id)===q||(o.customer_name||'').toLowerCase().includes(q)));
    list.innerHTML=rows.length?rows.map(card).join(''):'<p class="text-stone-500">Tidak ada pesanan yang cocok.</p>';
    list.querySelectorAll('[data-confirm]').forEach(btn=>btn.addEventListener('click',async()=>{
      if(!window.confirm(`Konfirmasi pembayaran tunai untuk pesanan ${btn.dataset.code}?`))return;
      btn.disabled=true;
      try{
        const d=await api(API.confirmPayment,{method:'POST',body:JSON.stringify({order_id:Number(btn.dataset.confirm)})});
        showMessage(msg,`${d.message} (${btn.dataset.code})`,'success');
        await load();
      }catch(err){showMessage(msg,err.message,'error');btn.disabled=false;}
    }));
  }
  async function load(){
    try{const d=await api(API.adminOrders);orders=d.orders;render();}
    catch(err){list.innerHTML=`<p class="notice error">${escapeHtml(err.message)}</p>`;}
  }
  search.addEventListener('input',render);
  filter.addEventListener('change',render);
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    const ref=String(new FormData(form).get('order_ref')||'').trim();
    const payload=/^\d+$/.test(ref)?{order_id:Number(ref)}:{order_code:ref.toUpperCase()};
    try{
      const d=await api(API.confirmPayment,{method:'POST',body:JSON.stringify(payload)});
      showMessage(msg,d.message,'success');
      form.reset();
      await load();
    }catch(err){showMessage(msg,err.message,'error');}
  });
  await load();
}
async function logout(){try{await api(API.logout,{method:'POST',body:JSON.stringify({})});location.href='login.html'}catch(e){alert(e.message)}}
document.addEventListener('DOMContentLoaded',()=>{initAuth();initHome();initCustomer();initBarber();initAdmin();document.querySelectorAll('[data-logout]').forEach(x=>x.addEventListener('click',logout));});
