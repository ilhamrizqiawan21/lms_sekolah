#!/usr/bin/env bash
# Audit style UI untuk rencana docs/UI_REDESIGN_CALM_ACADEMIC.md (bagian 1).
# Hanya membaca file; jalankan dari root repo: bash scripts/ui-audit.sh
# Angka = jumlah BARIS yang cocok (bukan jumlah kemunculan), agar sebanding antar audit.
set -u
cd "$(dirname "$0")/.."

JS=resources/js
PAGES=$JS/Pages
# tokens.css adalah sumber token (hex di sana sah), jadi dilaporkan terpisah dan tidak dihitung sebagai pelanggaran.
CSS_FILES=$(ls resources/css/*.css public/css/*.css 2>/dev/null | grep -v "resources/css/tokens.css")

count() { grep -rE "$1" "${@:2}" 2>/dev/null | wc -l; }
count_css() { cat $CSS_FILES | grep -cE "$1"; }
# <style> di dalam .vue ikut dihitung sebagai CSS
vue_style() { awk '/<style/{f=1} f{print} /<\/style>/{f=0}' $(find $JS -name '*.vue') | grep -cE "$1"; }
vue_tpl() { find $JS -name '*.vue' -print0 | xargs -0 awk -v pat="$1" '/<style/{s=1} /<\/style>/{s=0;next} !s && $0 ~ pat' | wc -l; }

echo "== Warna hardcoded"
echo "hex di template/script .vue (di luar <style>, tanpa href=\"#\"): $(find $JS -name '*.vue' -print0 | xargs -0 awk '/<style/{s=1} /<\/style>/{s=0;next} !s' | grep -E '#[0-9a-fA-F]{3,8}\b' | grep -vc 'href="#')"
echo "hex di <style> dalam .vue: $(vue_style '#[0-9a-fA-F]{3,8}\b')"
for f in $CSS_FILES; do echo "hex di $f: $(grep -cE '#[0-9a-fA-F]{3,8}\b' "$f")"; done
echo "hex di CSS di luar tokens.css (jumlah): $(count_css '#[0-9a-fA-F]{3,8}\b')"
echo "hex di tokens.css (sumber token, tidak dihitung): $(grep -cE '#[0-9a-fA-F]{3,8}\b' resources/css/tokens.css 2>/dev/null)"

echo "== !important"
for f in $CSS_FILES; do echo "$f: $(grep -c '!important' "$f")"; done
echo "di <style> dalam .vue: $(vue_style '!important')"
echo "total CSS: $(count_css '!important')"

echo "== Inline style"
echo "style=\"...\" di .vue: $(count 'style="' $JS --include=*.vue)"
echo ":style= di .vue: $(count ':style=' $JS --include=*.vue)"

echo "== Kelas deprecated"
echo "text-muted: $(count 'text-muted' $JS --include=*.vue)"
echo "text-body-secondary: $(count 'text-body-secondary' $JS --include=*.vue)"

echo "== Dark mode"
echo "selector [data-bs-theme=\"dark\"] di CSS: $(count_css 'data-bs-theme="dark"')"
echo "selector [data-bs-theme=\"dark\"] di <style> .vue: $(vue_style 'data-bs-theme="dark"')"

echo "== Komponen bersama vs mentah (Pages)"
echo "card mentah: $(count 'class="[^"]*\bcard\b' $PAGES --include=*.vue) | <Card: $(count '<Card\b' $PAGES --include=*.vue)"
echo "badge mentah: $(count 'class="[^"]*\bbadge\b' $PAGES --include=*.vue) | <Badge: $(count '<Badge\b' $PAGES --include=*.vue)"
echo "<table: $(count '<table' $PAGES --include=*.vue) | <TableWrapper: $(count '<TableWrapper' $PAGES --include=*.vue)"
echo "form-control/form-select mentah: $(count 'class="form-(control|select)' $PAGES --include=*.vue) | <TextInput/SelectInput/TextareaInput: $(count '<(TextInput|SelectInput|TextareaInput)\b' $PAGES --include=*.vue)"
echo "button.btn mentah: $(count '<button[^>]*class="[^"]*btn' $PAGES --include=*.vue) | <Button: $(count '<Button\b' $PAGES --include=*.vue)"
echo "teks kosong ('Belum ada'/'Tidak ada data'): $(count 'Belum ada|Tidak ada data' $PAGES --include=*.vue) | <EmptyState: $(count '<EmptyState' $PAGES --include=*.vue)"
echo "btn-primary: $(count 'btn-primary' $JS --include=*.vue) | btn-success: $(count 'btn-success' $JS --include=*.vue)"

echo "== Aksesibilitas"
echo "<th: $(count '<th[ >]' $JS --include=*.vue) | <th scope: $(count '<th scope' $JS --include=*.vue)"
echo "window.confirm/alert: $(count '\b(window\.)?(confirm|alert)\(' $JS --include=*.vue --include=*.ts)"
echo "overlay modal dengan role=dialog tanpa penanganan Escape:"
for f in $(grep -rlE 'role="dialog"|confirm-overlay' $JS --include=*.vue); do
  grep -qiE 'escape|keydown\.esc' "$f" || echo "  - $f"
done

echo "== Breakpoint (media query unik)"
{ cat $CSS_FILES; awk '/<style/{f=1} f{print} /<\/style>/{f=0}' $(find $JS -name '*.vue'); } | grep -ohE '@media[^{]+' | sed 's/ \+/ /g' | sort | uniq -c | sort -nr
