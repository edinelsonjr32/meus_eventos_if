<?php

namespace App\Support;

/**
 * Limpa HTML escrito em editores ricos (descrição do evento etc.): mantém a formatação e as imagens,
 * remove scripts, eventos (onclick...), iframes, estilos perigosos e endereços "javascript:".
 */
class HtmlSeguro
{
    private static ?\HTMLPurifier $purifier = null;

    public static function limpar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        try {
            $limpo = trim(self::purifier()->purify($html));
        } catch (\Throwable $e) {
            // Falha do purificador (ex.: pasta de cache sem permissão): nunca derruba a tela nem deixa passar HTML sem limpar.
            report($e);

            return trim(nl2br(e(strip_tags($html)))) ?: null;
        }

        // Editor vazio ("<p>&nbsp;</p>") não conta como conteúdo
        $visivel = preg_replace('/[\s\x{00A0}]+/u', '', strip_tags($limpo, '<img><table><hr>'));

        return $visivel === '' ? null : $limpo;
    }

    /** Como limpar(), mas sem imagens, figuras e tabelas: para textos de formulários de usuários (sem envio de arquivos). */
    public static function limparSimples(?string $html): ?string
    {
        $limpo = self::limpar($html);
        if ($limpo === null) {
            return null;
        }
        $limpo = preg_replace(['~<img\b[^>]*>~i', '~</?(figure|figcaption|table|thead|tbody|tr|th|td)\b[^>]*>~i'], '', $limpo);

        return self::limpar($limpo);
    }

    /** Como limpar(), mas sem imagens: mantém títulos, listas, links e tabelas (editais e regulamentos). */
    public static function limparSemImagens(?string $html): ?string
    {
        $limpo = self::limpar($html);
        if ($limpo === null) {
            return null;
        }
        $limpo = preg_replace(['~<img\b[^>]*>~i', '~</?(figure|figcaption)\b[^>]*>~i'], '', $limpo);

        return self::limpar($limpo);
    }

    /** Texto puro (sem tags nem entidades), com quebras de linha entre parágrafos: para contar caracteres ou guardar como texto. */
    public static function paraTexto(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $t = preg_replace(['~<br\s*/?>~i', '~</(p|div|h[1-6]|li|blockquote|tr)>~i'], "\n", $html);
        $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\u{00A0}", ' ', $t)));
    }

    /** Número de caracteres do texto visível (ignora tags). */
    public static function tamanho(?string $html): int
    {
        return mb_strlen(self::paraTexto($html));
    }

    /** Texto simples antigo (sem tags) mantém as quebras de linha; HTML é limpo. Pronto para exibir com {!! !!}. */
    public static function paraExibir(?string $texto): string
    {
        if ($texto === null || trim($texto) === '') {
            return '';
        }

        return $texto === strip_tags($texto) ? nl2br(e($texto)) : (string) self::limpar($texto);
    }

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        // Pasta de cache do purificador: a do projeto, ou a temporária do sistema; sem nenhuma gravável, funciona sem cache
        $cache = null;
        foreach ([storage_path('framework/cache/purifier'), sys_get_temp_dir() . '/purifier-' . md5(base_path())] as $candidata) {
            if (!is_dir($candidata)) {
                @mkdir($candidata, 0775, true);
            }
            if (is_dir($candidata) && is_writable($candidata)) {
                $cache = $candidata;
                break;
            }
        }

        $c = \HTMLPurifier_Config::createDefault();
        $c->set('Core.Encoding', 'UTF-8');
        if ($cache) {
            $c->set('Cache.SerializerPath', $cache);
        } else {
            $c->set('Cache.DefinitionImpl', null);
        }
        $c->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $c->set('HTML.DefinitionID', 'html-seguro');
        $c->set('HTML.DefinitionRev', 1);
        $c->set('HTML.Allowed', implode(',', [
            'p[style|class]', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'span[style]',
            'h2[style]', 'h3[style]', 'h4[style]', 'ul', 'ol[start]', 'li', 'blockquote', 'pre', 'code',
            'a[href|target|rel|title]', 'img[src|alt|width|height|style|class]', 'figure[class|style]', 'figcaption',
            'table[style|class|border|cellpadding|cellspacing]', 'thead', 'tbody', 'tr', 'th[style|colspan|rowspan]', 'td[style|colspan|rowspan]',
        ]));
        $c->set('CSS.AllowedProperties', ['color', 'background-color', 'text-align', 'font-size', 'font-family', 'font-weight', 'font-style', 'text-decoration', 'width', 'height', 'border', 'padding', 'margin-left', 'margin-right']);
        $c->set('Attr.AllowedClasses', ['image', 'image_resized', 'image-style-side', 'image-style-align-left', 'image-style-align-right', 'image-style-align-center', 'image-style-block-align-left', 'image-style-block-align-right', 'table', 'text-tiny', 'text-small', 'text-big', 'text-huge']);
        $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $c->set('HTML.TargetBlank', true);
        $c->set('HTML.TargetNoopener', true);
        $c->set('AutoFormat.RemoveEmpty', false);
        $c->set('HTML.SafeIframe', false);

        // <figure> e <figcaption> (HTML5) não existem no HTML 4: registra-os para o editor de imagens
        if ($def = $c->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
        }

        return self::$purifier = new \HTMLPurifier($c);
    }
}
