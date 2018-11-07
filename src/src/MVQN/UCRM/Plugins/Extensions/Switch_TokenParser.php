<?php

//To be added under Twig/TokenParser/

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_TokenParser_Switch extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $parser = $this->parser;
        $stream = $parser->getStream();

        $default = null;
        $cases = array();
        $end = false;

        $name = $parser->getExpressionParser()->parseExpression();
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $stream->expect(Twig_Token::TEXT_TYPE);
        $stream->expect(Twig_Token::BLOCK_START_TYPE);
        while (!$end)
        {
            $v = $stream->next();
            switch ($v->getValue()) {
                case 'default':
                    $stream->expect(Twig_Token::BLOCK_END_TYPE);
                    $default = $parser->subparse(array($this, 'decideIfEnd'));
                    break;

                case 'case':
                    $expr = $parser->getExpressionParser()->parseExpression();
                    $stream->expect(Twig_Token::BLOCK_END_TYPE);
                    $body = $parser->subparse(array($this, 'decideIfFork'));
                    $cases[] = $expr;
                    $cases[] = $body;
                    break;

                case 'endswitch':
                    $end = true;
                    break;

                default:
                    throw new Twig_Error_Syntax(sprintf('Unexpected end of template. Twig was looking for the following tags "case", "default", or "endswitch" to close the "switch" block started at line %d)', $lineno), -1);
            }
        }

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Node_Switch($name,new Twig_Node($cases), $default, $token->getLine(), $this->getTag());
    }

    public function decideIfFork($token)
    {
        return $token->test(array('case', 'default', 'endswitch'));
    }

    public function decideIfEnd($token)
    {
        return $token->test(array('endswitch'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'switch';
    }
}