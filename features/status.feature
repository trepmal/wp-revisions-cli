Feature: Check revision status.

  Scenario: Check default revision status
    Given a WP install

    When I run `wp revisions status`
    Then STDOUT should be:
      """
      Success: WP_POST_REVISIONS is true. Keeps all revisions.
      """
