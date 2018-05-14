<?php

namespace Tests\integration\store\query;

use Tests\ARC2_TestCase;

/**
 * Tests for query method - focus on how the system reacts, when errors occur.
 */
class ErrorHandlingInQueriesTest extends ARC2_TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = \ARC2::getStore($this->dbConfig);
        $this->fixture->drop();
        $this->fixture->setup();
    }

    /**
     * What if a result variable is not used in query.
     */
    public function testResultVariableNotUsedInQuery()
    {
        $res = $this->fixture->query('
            SELECT ?not_used_in_query ?s WHERE {
                ?s ?p ?o .
            }
        ');

        $this->assertEquals(
            [
                'query_type' => 'select',
                'result' => [
                    'variables' => [
                        'not_used_in_query', 's'
                    ],
                    'rows' => [
                    ],
                ],
                'query_time' => $res['query_time']
            ],
            $res
        );

        $this->assertEquals(
            [
                'Result variable "not_used_in_query" not used in query. via ARC2_StoreSelectQueryHandler',
                "Unknown column 'V1.val' in 'field list' via ARC2_StoreSelectQueryHandler"
            ],
            $this->fixture->errors
        );
    }
}
