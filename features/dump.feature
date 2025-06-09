Feature: Revisions

  Scenario: Dump revisions
    Given a WP install

    When I run `wp revisions generate 93`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      279
      """

    When I run `wp revisions dump --yes`
    Then STDOUT should contain:
      """
      Success: Finished removing all revisions.
      """

    When I run `wp revisions list --format=json`
    Then STDOUT should contain:
      """
      []
      """
