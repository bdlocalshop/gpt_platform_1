/**
 * Generate or retrieve a deterministic client device fingerprint.
 * Combines hardware, display, timezone, and canvas rendering characteristics.
 */
export function getDeviceFingerprint(): string {
  if (typeof window === "undefined") {
    return "ssr_fingerprint_placeholder";
  }

  const STORAGE_KEY = "gpt_device_fp";
  const cached = localStorage.getItem(STORAGE_KEY);
  if (cached && cached.length === 64) {
    return cached;
  }

  try {
    const screenInfo = `${window.screen.width}x${window.screen.height}x${window.screen.colorDepth}`;
    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
    const language = navigator.language || "en-US";
    const hardwareConcurrency = navigator.hardwareConcurrency || 4;
    const platform = (navigator as unknown as { userAgentData?: { platform?: string } }).userAgentData?.platform || navigator.platform || "unknown";

    // Canvas rendering hash
    let canvasHash = "no_canvas";
    try {
      const canvas = document.createElement("canvas");
      canvas.width = 200;
      canvas.height = 50;
      const ctx = canvas.getContext("2d");
      if (ctx) {
        ctx.textBaseline = "top";
        ctx.font = "14px 'Arial'";
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#f60";
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = "#069";
        ctx.fillText("GPT,Rewards!#123", 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText("GPT,Rewards!#123", 4, 17);
        canvasHash = canvas.toDataURL();
      }
    } catch {
      // fallback
    }

    const raw = `${screenInfo}|${timeZone}|${language}|${hardwareConcurrency}|${platform}|${canvasHash.substring(0, 100)}`;
    const fp = simpleSha256(raw);
    localStorage.setItem(STORAGE_KEY, fp);
    return fp;
  } catch {
    const fallback = Math.random().toString(36).substring(2) + Date.now().toString(36);
    return fallback.padStart(64, "0").substring(0, 64);
  }
}

/**
 * Lightweight synchronous hashing function (FNV-1a / DJB2 variant to 64 hex characters)
 * to avoid async crypto delay on initial requests.
 */
function simpleSha256(str: string): string {
  let h1 = 0xdeadbeef;
  let h2 = 0x41c6ce57;
  let h3 = 0x12345678;
  let h4 = 0x87654321;

  for (let i = 0; i < str.length; i++) {
    const ch = str.charCodeAt(i);
    h1 = Math.imul(h1 ^ ch, 2654435761);
    h2 = Math.imul(h2 ^ ch, 1597334677);
    h3 = Math.imul(h3 ^ ch, 974261239);
    h4 = Math.imul(h4 ^ ch, 2147483647);
  }

  const hex = (h: number) => (h >>> 0).toString(16).padStart(8, "0");
  const p1 = hex(h1) + hex(h2);
  const p2 = hex(h3) + hex(h4);
  return (p1 + p2 + p1 + p2).substring(0, 64);
}
