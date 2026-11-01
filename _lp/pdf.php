<?php
/* Générateur PDF minimal (A4, Helvetica, encodage WinAnsi), sans dépendance externe.
   Suffisant pour contrats, conventions, devis, factures, convocations et attestations. */

final class Pdf {
  private array $pages = [];
  private string $cur = '';
  public float $y = 0;
  public float $W = 595.28, $H = 841.89, $ml = 56, $mr = 56, $mt = 64, $mb = 70;
  public $onPage = null;   // callable(Pdf $pdf, int $n) : en-tête et pied de page
  private string $title;
  private ?array $img = null;   // logo : ['data' => jpeg, 'w' => px, 'h' => px]
  private bool $imgUsed = false;

  /* Charge une image (PNG ou JPEG) via GD et la convertit en JPEG sur fond blanc pour l'intégrer au PDF. */
  public function setLogo(string $path): bool {
    if (!is_file($path) || !function_exists('imagecreatefromstring')) return false;
    $src = @imagecreatefromstring((string)file_get_contents($path));
    if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    ob_start(); imagejpeg($dst, null, 92); $data = (string)ob_get_clean();
    $this->img = ['data' => $data, 'w' => $w, 'h' => $h];
    return true;
  }
  public function hasLogo(): bool { return $this->img !== null; }
  /* Dessine le logo en (x, y) haut-gauche, hauteur $hPt en points. */
  public function logo(float $x, float $y, float $hPt): float {
    if (!$this->img) return 0;
    $wPt = $hPt * $this->img['w'] / $this->img['h'];
    $this->cur .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $wPt, $hPt, $x, $this->H - $y - $hPt);
    $this->imgUsed = true;
    return $wPt;
  }
  private const HELV = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
  private const HELVB = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

  public function __construct(string $title = '') { $this->title = $title; }

  public static function enc(string $s): string {
    $s = str_replace(["\u{202F}", "\u{2009}", "\u{2011}"], [' ', ' ', '-'], $s);
    $r = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
    return $r === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $r;
  }
  public function width(string $s, float $size, bool $bold = false): float {
    $t = self::enc($s); $w = 0; $tbl = $bold ? self::HELVB : self::HELV;
    for ($i = 0, $n = strlen($t); $i < $n; $i++) {
      $c = ord($t[$i]);
      if ($c >= 32 && $c <= 126) { $w += $tbl[$c - 32]; continue; }
      $w += match (true) { $c === 0x97 => 1000, $c === 0x92 || $c === 0x91 => 222, $c === 0xB7 || $c === 0xA0 => 278, $c >= 0xC0 && $c <= 0xC6 => 667, $c === 0xC7 => 722, $c >= 0xC8 && $c <= 0xCB => 667, $c >= 0xCC && $c <= 0xCF => 278, $c >= 0xE0 && $c <= 0xE6 => 556, $c === 0xE7 => 500, $c >= 0xEC && $c <= 0xEF => 278, default => 556 };
    }
    return $w * $size / 1000;
  }
  private static function esc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::enc($s)); }
  private static function rgb(string $hex): string { $hex = ltrim($hex, '#'); return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255); }

  public function page(): void {
    if ($this->cur !== '') $this->pages[] = $this->cur;
    $this->cur = ' ';
    $this->y = $this->mt;
    if ($this->onPage) ($this->onPage)($this, count($this->pages) + 1);
  }
  public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $color = '1F2A37'): void {
    $this->cur .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", self::rgb($color), $bold ? 'F2' : 'F1', $size, $x, $this->H - $y, self::esc($s));
  }
  public function textRight(float $xr, float $y, string $s, float $size = 10, bool $bold = false, string $color = '1F2A37'): void { $this->text($xr - $this->width($s, $size, $bold), $y, $s, $size, $bold, $color); }
  public function line(float $x1, float $y1, float $x2, float $y2, string $color = 'C9C2B4', float $w = 0.6): void {
    $this->cur .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::rgb($color), $w, $x1, $this->H - $y1, $x2, $this->H - $y2);
  }
  public function rect(float $x, float $y, float $w, float $h, string $fill): void {
    $this->cur .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", self::rgb($fill), $x, $this->H - $y - $h, $w, $h);
  }
  public function wrap(string $s, float $width, float $size, bool $bold = false): array {
    $out = [];
    foreach (explode("\n", $s) as $para) {
      $words = preg_split('/ +/', $para); $line = '';
      foreach ($words as $w) {
        $try = $line === '' ? $w : "$line $w";
        if ($this->width($try, $size, $bold) <= $width || $line === '') $line = $try; else { $out[] = $line; $line = $w; }
      }
      $out[] = $line;
    }
    return $out;
  }
  public function ensure(float $h): void { if ($this->y + $h > $this->H - $this->mb) $this->page(); }
  /* Paragraphe justifié à gauche avec saut de page automatique. */
  public function para(string $s, float $size = 10, bool $bold = false, string $color = '1F2A37', float $lead = 1.45, ?float $x = null, ?float $w = null, float $after = 6): void {
    $x ??= $this->ml; $w ??= $this->W - $this->ml - $this->mr;
    foreach ($this->wrap($s, $w, $size, $bold) as $l) { $this->ensure($size * $lead); $this->y += $size * $lead; $this->text($x, $this->y, $l, $size, $bold, $color); }
    $this->y += $after;
  }
  public function h(string $s, float $size = 13, string $color = '21406A'): void { $this->ensure($size * 3); $this->y += 10; $this->para($s, $size, true, $color, 1.3, null, null, 4); }
  public function bullets(array $items, float $size = 10): void {
    foreach ($items as $it) {
      $lines = $this->wrap($it, $this->W - $this->ml - $this->mr - 14, $size);
      foreach ($lines as $i => $l) { $this->ensure($size * 1.45); $this->y += $size * 1.45; if ($i === 0) $this->text($this->ml, $this->y, '–', $size, false, '2F6F73'); $this->text($this->ml + 14, $this->y, $l, $size); }
      $this->y += 2;
    }
    $this->y += 4;
  }
  /* Tableau simple : $cols = [[largeur, alignement 'L'|'R'], …] */
  public function table(array $cols, array $head, array $rows, float $size = 9.5): void {
    $x0 = $this->ml; $pad = 5;
    $draw = function (array $cells, bool $isHead) use ($cols, $x0, $pad, $size) {
      $wrapped = []; $hmax = 1;
      foreach ($cells as $i => $c) { $wrapped[$i] = $this->wrap((string)$c, $cols[$i][0] - 2 * $pad, $size, $isHead); $hmax = max($hmax, count($wrapped[$i])); }
      $rowH = $hmax * $size * 1.35 + 2 * $pad;
      $this->ensure($rowH);
      if ($isHead) $this->rect($x0, $this->y, array_sum(array_column($cols, 0)), $rowH, '21406A');
      $x = $x0;
      foreach ($cells as $i => $c) {
        foreach ($wrapped[$i] as $k => $l) {
          $yy = $this->y + $pad + ($k + 1) * $size * 1.35 - 2;
          if (($cols[$i][1] ?? 'L') === 'R') $this->textRight($x + $cols[$i][0] - $pad, $yy, $l, $size, $isHead, $isHead ? 'FFFFFF' : '1F2A37');
          else $this->text($x + $pad, $yy, $l, $size, $isHead, $isHead ? 'FFFFFF' : '1F2A37');
        }
        $x += $cols[$i][0];
      }
      $this->y += $rowH;
      $this->line($x0, $this->y, $x0 + array_sum(array_column($cols, 0)), $this->y);
    };
    $draw($head, true);
    foreach ($rows as $r) $draw($r, false);
    $this->y += 8;
  }

  public function output(): string {
    if ($this->cur !== '') { $this->pages[] = $this->cur; $this->cur = ''; }
    $objs = []; $n = count($this->pages);
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = []; for ($i = 0; $i < $n; $i++) $kids[] = (5 + 2 * $i) . ' 0 R';
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $n >>";
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    foreach ($this->pages as $i => $content) {
      $p = 5 + 2 * $i; $c = $p + 1;
      $objs[$p] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $this->W, $this->H, $c);
      $z = gzcompress($content);
      $objs[$c] = '<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream";
    }
    $imgObj = null;
    if ($this->img && $this->imgUsed) {
      $imgObj = count($objs) + 1;
      $objs[$imgObj] = '<< /Type /XObject /Subtype /Image /Width ' . $this->img['w'] . ' /Height ' . $this->img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($this->img['data']) . " >>\nstream\n" . $this->img['data'] . "\nendstream";
      foreach ($this->pages as $i => $content) $objs[5 + 2 * $i] = str_replace('/F2 4 0 R >>', '/F2 4 0 R >> /XObject << /Im1 ' . $imgObj . ' 0 R >>', $objs[5 + 2 * $i]);
    }
    $info = count($objs) + 1;
    $objs[$info] = '<< /Title (' . self::esc($this->title) . ') /Producer (Lead Performant) /CreationDate (D:' . date('YmdHis') . ') >>';
    $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    ksort($objs);
    foreach ($objs as $k => $o) { $off[$k] = strlen($out); $out .= "$k 0 obj\n$o\nendobj\n"; }
    $x = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($objs as $k => $o) $out .= sprintf("%010d 00000 n \n", $off[$k]);
    $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$x\n%%EOF";
    return $out;
  }
}
