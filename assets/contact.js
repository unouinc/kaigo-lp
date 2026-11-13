/*!
 * 介護領域向けLP（unou.design/kaigo/）無料相談フォーム
 * <form data-kaigo-contact> を自動で検知して、/kaigo/api/contact.php に送信します。
 * 読み込み：<script src="assets/contact.js" defer></script>
 */
(function(){
	var forms=document.querySelectorAll('form[data-kaigo-contact]');
	if(!forms.length)return;
	var EP=(document.currentScript&&document.currentScript.getAttribute('data-endpoint'))||'/kaigo/api/contact.php';
	var RULES={
		company:function(v){return v?'':'法人名・施設名を入力してください。';},
		name:function(v){return v?'':'お名前を入力してください。';},
		tel:function(v){return !v?'電話番号を入力してください。':(/^[0-9０-９\-－‐+()（）\s]{10,20}$/.test(v)?'':'電話番号を正しく入力してください。');},
		email:function(v){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)?'':'メールアドレスを正しく入力してください。';}
	};
	var utm=(function(){try{var q=new URLSearchParams(location.search),o=[];['utm_source','utm_medium','utm_campaign','utm_content'].forEach(function(k){if(q.get(k))o.push(k.replace('utm_','')+'='+q.get(k));});return o.join(' / ');}catch(e){return '';}})();
	Array.prototype.forEach.call(forms,function(form){
		var start=Date.now();
		form.setAttribute('novalidate','');
		var hp=document.createElement('input');
		hp.type='text';hp.name='website';hp.tabIndex=-1;hp.autocomplete='off';hp.setAttribute('aria-hidden','true');
		hp.style.cssText='position:absolute!important;left:-9999px;width:1px;height:1px;opacity:0;';
		form.appendChild(hp);
		var btn=form.querySelector('[type=submit]');
		var label=btn?btn.textContent:'';
		var status=form.querySelector('[data-bc-status]');
		var done=document.querySelector(form.getAttribute('data-done')||'[data-bc-done]');
		function val(n){var el=form.elements[n];return el?(el.value||'').trim():'';}
		function setErr(n,t){
			var el=form.elements[n];if(!el)return;
			var box=el.closest('[data-bc-field]')||el.parentNode;
			box.classList.toggle('is-error',!!t);
			var e=box.querySelector('[data-bc-error]');if(e)e.textContent=t||'';
		}
		Object.keys(RULES).forEach(function(n){
			var el=form.elements[n];if(!el)return;
			el.addEventListener('blur',function(){setErr(n,RULES[n](val(n)));});
		});
		form.addEventListener('submit',function(ev){
			ev.preventDefault();
			if(status)status.textContent='';
			var ok=true;
			Object.keys(RULES).forEach(function(n){var t=RULES[n](val(n));setErr(n,t);if(t)ok=false;});
			if(!ok){var f=form.querySelector('.is-error input,.is-error textarea');if(f)f.focus();return;}
			if(btn){btn.disabled=true;btn.textContent='送信中…';}
			form.classList.add('is-sending');
			fetch(EP,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
				company:val('company'),name:val('name'),tel:val('tel'),email:val('email'),message:val('message'),utm:utm,
				website:hp.value,ts:start
			})}).then(function(r){return r.json();}).then(function(j){
				if(j&&j.ok){
					form.classList.add('is-sent');
					if(done){form.hidden=true;done.hidden=false;}
					else if(status){status.textContent='送信しました。2営業日以内にご連絡いたします。';}
					window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'kaigo_contact_submit'});
					if(typeof window.gtag==='function')window.gtag('event','generate_lead',{form_name:'kaigo_contact'});
					return;
				}
				if(j&&j.errors)Object.keys(j.errors).forEach(function(k){setErr(k,j.errors[k]);});
				if(status)status.textContent=(j&&j.message)||'入力内容をご確認ください。';
			}).catch(function(){
				if(status)status.textContent='送信できませんでした。通信環境をご確認のうえ、再度お試しください。';
			}).finally(function(){
				form.classList.remove('is-sending');
				if(btn){btn.disabled=false;btn.textContent=label;}
			});
		});
	});
})();
