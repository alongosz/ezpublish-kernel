<?php
/**
 * File containing the OutputGeneratorException class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Common\Output\Exceptions;

/**
 * Invalid output generation
 */
class OutputGeneratorException extends \RuntimeException
{
    /**
     * Construct from error message
     *
     * @param string $message
     */
    public function __construct( $message )
    {
        parent::__construct(
            'Output visiting failed: ' . $message
        );
    }
}
