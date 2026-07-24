PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.31
Configuration: /var/www/html/web/core/phpunit.xml.dist

DDDD......................                                        26 / 26 (100%)

HTML output was generated.
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-25-10145687.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-26-10145687.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-27-10145687.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-28-63846832.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-29-63846832.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-30-63846832.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-31-58781949.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-32-58781949.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-33-58781949.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-34-62111446.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-35-62111446.html
http://web/sites/simpletest/browser_output/Drupal_Tests_ticket_management_Functional_TicketApiFunctionalTest-36-62111446.html


Time: 00:15.981, Memory: 8.00 MB

4 tests triggered 1 deprecation:

1) /var/www/html/web/core/lib/Drupal/Core/Test/HttpClientMiddleware/TestHttpClientMiddleware.php:51
Since twig/twig 3.28: The "Drupal\Core\Template\TwigSandboxPolicy::checkSecurity()" method will take a 4th "array $tests" argument in 4.0; not declaring it is deprecated.

Triggered by:

* Drupal\Tests\ticket_management\Functional\TicketApiFunctionalTest::testCreateTicketReturns201 (2 times)
  /var/www/html/web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php:159

* Drupal\Tests\ticket_management\Functional\TicketApiFunctionalTest::testInvalidStatusTransitionReturns422 (2 times)
  /var/www/html/web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php:126

* Drupal\Tests\ticket_management\Functional\TicketApiFunctionalTest::testListTicketsReturns200 (2 times)
  /var/www/html/web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php:147

* Drupal\Tests\ticket_management\Functional\TicketApiFunctionalTest::testValidStatusTransitionReturns200 (2 times)
  /var/www/html/web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php:108

OK, but there were issues!
Tests: 26, Assertions: 120, Deprecations: 1.