<?php
/**
 * Prisma — Manifiesto
 * Renders info/manifiesto-prisma.md as a styled web page using the shared layout.
 */
require_once __DIR__ . '/lib/layout.php';

// ── Read and convert MD to HTML ──────────────────────────────────────
$md_path = __DIR__ . '/info/manifiesto-prisma.md';
$md = file_exists($md_path) ? file_get_contents($md_path) : '';

/**
 * Minimal Markdown-to-HTML converter (no external deps, PHP 7.x compatible).
 * Handles: headings, bold, italic, links, paragraphs, lists, tables, hr, blockquotes.
 */
function prisma_md_to_html($text) {
    $lines = explode("\n", $text);
    $html = '';
    $in_list = false;
    $in_table = false;
    $in_blockquote = false;
    $paragraph = '';

    $flush_paragraph = function () use (&$paragraph, &$html) {
        if (trim($paragraph) !== '') {
            $html .= '<p>' . trim($paragraph) . "</p>\n";
            $paragraph = '';
        }
    };

    $inline = function ($line) {
        // Links [text](url)
        $line = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $line);
        // Bold **text**
        $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
        // Italic *text* (not inside strong)
        $line = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $line);
        // Inline code `text`
        $line = preg_replace('/`([^`]+)`/', '<code>$1</code>', $line);
        return $line;
    };

    foreach ($lines as $raw_line) {
        $line = $raw_line;

        // Horizontal rule
        if (preg_match('/^---+\s*$/', $line)) {
            $flush_paragraph();
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            if ($in_table) { $html .= "</tbody></table>\n"; $in_table = false; }
            $html .= "<hr>\n";
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            $flush_paragraph();
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            if ($in_table) { $html .= "</tbody></table>\n"; $in_table = false; }
            $level = strlen($m[1]);
            $text = $inline($m[2]);
            $html .= "<h{$level}>{$text}</h{$level}>\n";
            continue;
        }

        // Table row
        if (preg_match('/^\|(.+)\|\s*$/', $line, $m)) {
            $flush_paragraph();
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            $cells = array_map('trim', explode('|', trim($m[1])));
            // Separator row (---|---)
            if (preg_match('/^[\s\-:|]+$/', $m[1])) {
                continue; // skip separator
            }
            if (!$in_table) {
                $html .= "<table>\n<thead><tr>\n";
                foreach ($cells as $cell) {
                    $html .= '<th>' . $inline($cell) . "</th>\n";
                }
                $html .= "</tr></thead>\n<tbody>\n";
                $in_table = true;
            } else {
                $html .= "<tr>\n";
                foreach ($cells as $cell) {
                    $html .= '<td>' . $inline($cell) . "</td>\n";
                }
                $html .= "</tr>\n";
            }
            continue;
        } else if ($in_table) {
            $html .= "</tbody></table>\n";
            $in_table = false;
        }

        // Blockquote
        if (preg_match('/^>\s*(.*)$/', $line, $m)) {
            $flush_paragraph();
            if (!$in_blockquote) {
                $html .= "<blockquote>\n";
                $in_blockquote = true;
            }
            $html .= $inline($m[1]) . "\n";
            continue;
        } else if ($in_blockquote) {
            $html .= "</blockquote>\n";
            $in_blockquote = false;
        }

        // Unordered list
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $flush_paragraph();
            if (!$in_list) {
                $html .= "<ul>\n";
                $in_list = true;
            }
            $html .= '<li>' . $inline($m[1]) . "</li>\n";
            continue;
        } else if ($in_list && trim($line) === '') {
            $html .= "</ul>\n";
            $in_list = false;
        }

        // Empty line = end of paragraph
        if (trim($line) === '') {
            $flush_paragraph();
            continue;
        }

        // Normal text line
        $paragraph .= ($paragraph !== '' ? ' ' : '') . $inline($line);
    }

    // Flush remaining
    $flush_paragraph();
    if ($in_list) $html .= "</ul>\n";
    if ($in_table) $html .= "</tbody></table>\n";
    if ($in_blockquote) $html .= "</blockquote>\n";

    return $html;
}

$content_html = prisma_md_to_html($md);

// ── Page output ──────────────────────────────────────────────────────
page_header(
    'Manifiesto',
    'El manifiesto de Prisma: por qué existe, qué creemos, cómo funciona. Transparencia radical contra la polarización.',
    'manifiesto'
);
?>

<section class="page-top">
  <p class="eyebrow">Manifiesto</p>
  <h1>Manifiesto Prisma</h1>
  <p>Rompiendo las paredes de tu burbuja digital</p>
</section>

<section class="content">
  <?= $content_html ?>
</section>

<?php page_footer(); ?>
