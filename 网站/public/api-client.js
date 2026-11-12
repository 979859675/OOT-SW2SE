'use strict';
// Shared response/file checks; no save parsing or editing algorithms in the browser.
(function (root) {
  async function requestJson(url, options, fetcher = fetch) {
    let response;
    try { response = await fetcher(url, options); }
    catch { throw Error('无法连接后端服务。请确认 Node.js 服务正在运行，并通过它提供的网址打开网页。'); }
    if (!response.headers.get('content-type')?.toLowerCase().includes('application/json')) {
      const hint = response.status === 413 ? '上传被代理服务器拒绝，请检查上传大小限制。' : '接口返回了网页而不是 JSON。请运行“cd 网站 && npm start”，并确认 /api/ 请求转发到 Node.js 后端，不能只部署 public 静态文件。';
      throw Error(`后端连接异常（HTTP ${response.status}）：${hint}`);
    }
    let data;
    try { data = await response.json(); }
    catch { throw Error('后端返回了无效的 JSON 数据，请检查后端或反向代理配置。'); }
    if (!response.ok) throw Error(data?.error || `请求失败（HTTP ${response.status}）`);
    return data;
  }
  function validateFileSelection(files) {
    const paths = new Set(files.map(file => file.path));
    const hasPair = files.some(file => /(?:^|\/)progress\.sav$/.test(file.path) && paths.has(file.path.replace(/progress\.sav$/, 'caption.sav')));
    if (!hasPair) throw Error('请选择同一存档目录中的 progress.sav 和 caption.sav。手动选择时请按住 Ctrl（Mac 为 Command）同时选中这两个文件，或直接使用“选择备份文件夹”。');
  }
  const api = { requestJson, validateFileSelection };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.WebApi = api;
})(globalThis);
