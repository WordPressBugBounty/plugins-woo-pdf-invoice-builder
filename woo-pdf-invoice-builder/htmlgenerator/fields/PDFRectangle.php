<?php

namespace rnwcinv\htmlgenerator\fields;

/**
 * PDFRectangle
 *
 * A decoration-only block that renders as an empty styled div.
 * Background color, border, and dimensions come from the field's styles.
 */
class PDFRectangle extends PDFFieldBase
{

    protected function InternalGetHTML()
    {
        return '';
    }
}
