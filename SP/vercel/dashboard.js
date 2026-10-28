(() => {
  const money = value => 'Rp' + new Intl.NumberFormat('id-ID', {notation: 'compact', maximumFractionDigits: 1}).format(value);
  const drawChart = config => {
    const canvas = document.getElementById(config.id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const labels = config.labels || [];
    const values = (config.values || []).map(value => Number(value) || 0);
    const palette = {ink:'#292923',muted:'#89877f',grid:'#eee9e1',green:'#788b68',orange:'#ef805c',pale:'#e7eee2'};
    const paint = () => {
      const ratio = window.devicePixelRatio || 1;
      const width = Math.max(280, canvas.clientWidth);
      const height = 220;
      canvas.width = width * ratio;
      canvas.height = height * ratio;
      canvas.style.height = height + 'px';
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.clearRect(0, 0, width, height);
      const pad = {left: config.currency ? 62 : 34, right: 14, top: 16, bottom: 35};
      const plotW = width - pad.left - pad.right;
      const plotH = height - pad.top - pad.bottom;
      if (!values.length || !values.some(Boolean)) {
        ctx.font = '12px DM Sans, sans-serif'; ctx.fillStyle = palette.muted; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(config.empty || 'Belum ada data', width / 2, height / 2); return;
      }
      const max = Math.max(1, ...values) * 1.12;
      ctx.font = '10px DM Sans, sans-serif'; ctx.textBaseline = 'middle';
      for (let step = 0; step <= 4; step++) {
        const y = pad.top + plotH - plotH * step / 4;
        ctx.strokeStyle = palette.grid; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
        ctx.fillStyle = palette.muted; ctx.textAlign = 'right';
        const label = config.currency ? money(max * step / 4) : Math.round(max * step / 4).toString();
        ctx.fillText(label, pad.left - 8, y);
      }
      if (config.kind === 'bar') {
        const slot = plotW / values.length;
        const barWidth = Math.min(38, slot * .56);
        values.forEach((value, i) => {
          const barH = plotH * value / max;
          const x = pad.left + slot * i + (slot - barWidth) / 2;
          const y = pad.top + plotH - barH;
          const gradient = ctx.createLinearGradient(0, y, 0, pad.top + plotH);
          gradient.addColorStop(0, palette.green); gradient.addColorStop(1, palette.pale);
          ctx.fillStyle = gradient; ctx.beginPath(); ctx.roundRect(x, y, barWidth, Math.max(2, barH), [5,5,0,0]); ctx.fill();
          ctx.fillStyle = palette.muted; ctx.textAlign = 'center'; ctx.fillText(labels[i] || '', x + barWidth / 2, height - 13);
          if (labels.length <= 10 && barH > 18) { ctx.fillStyle = palette.ink; ctx.fillText(String(value), x + barWidth / 2, y - 8); }
          canvas.title = (labels[i] || '') + ': ' + value;
        });
      } else {
        const points = values.map((value, i) => ({x: pad.left + (values.length < 2 ? plotW / 2 : plotW * i / (values.length - 1)), y: pad.top + plotH - plotH * value / max}));
        if (points.length) {
          const fill = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
          fill.addColorStop(0, '#ef805c38'); fill.addColorStop(1, '#ef805c00');
          ctx.beginPath(); points.forEach((point, i) => i ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
          ctx.lineTo(points[points.length - 1].x, pad.top + plotH); ctx.lineTo(points[0].x, pad.top + plotH); ctx.closePath(); ctx.fillStyle = fill; ctx.fill();
          ctx.beginPath(); points.forEach((point, i) => i ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
          ctx.strokeStyle = palette.orange; ctx.lineWidth = 3; ctx.lineJoin = 'round'; ctx.stroke();
          points.forEach((point, i) => {
            ctx.beginPath(); ctx.arc(point.x, point.y, 4, 0, Math.PI * 2); ctx.fillStyle = '#fff'; ctx.fill(); ctx.strokeStyle = palette.orange; ctx.lineWidth = 2; ctx.stroke();
            ctx.fillStyle = palette.muted; ctx.textAlign = 'center'; ctx.fillText(labels[i] || '', point.x, height - 13);
            canvas.title = (labels[i] || '') + ': ' + (config.currency ? money(values[i]) : values[i]);
          });
        }
      }
    };
    paint();
    window.addEventListener('resize', paint, {passive: true});
  };
  (window.shopCharts || []).forEach(drawChart);
})();
