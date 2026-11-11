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

        $limpo = trim(self::purifier()->purify($html));

        // Editor vazio ("<p>&nbsp;</p>") não conta como conteúdo
        $visivel = preg_replace('/[\s\x{00A0}]+/u', '', strip_tags($limpo, '<img><table><hr>'));

        return $visivel === '' ? null : $limpo;
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

        $cache = storage_path('framework/cache/purifier');
        if (!is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $c = \HTMLPurifier_Config::createDefault();
        $c->set('Core.Encoding', 'UTF-8');
        $c->set('Cache.SerializerPath', is_writable($cache) ? $cache : null);
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
