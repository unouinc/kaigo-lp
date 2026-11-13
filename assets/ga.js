/*
 * Googleアナリティクス（GA4）の読み込み
 * 各ページの <head> 内に次の1行を入れてください（unou側で用意済み・編集不要）
 *   <script src="/kaigo/assets/ga.js"></script>
 * 測定ID：G-3TYWNC1DGL（プロパティ：unou.design）
 * お問い合わせの送信完了は contact.js が generate_lead イベントとして送ります
 */
(function () {
  var id = 'G-3TYWNC1DGL';
  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
  document.head.appendChild(s);
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', id);
})();
