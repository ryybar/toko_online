(() => {
  const ping = () => fetch('api/auth.php?heartbeat=1', {credentials:'same-origin', cache:'no-store'}).catch(() => {});
  window.setInterval(ping, 60000);
})();
