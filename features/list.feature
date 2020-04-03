Feature: Revisions

  Scenario: Generate, list, remove revisions
    Given a WP install

    When I run `wp revisions list --format=json`
    Then STDOUT should contain:
      """
      []
      """

    When I run `wp revisions generate`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      45
      """

    When I run `wp revisions generate 5 --post_type=page`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      55
      """

    When I run `wp revisions generate 5 --post_id=1`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      60
      """

    When I run `wp revisions clean 5 --post_id=1`
    Then STDOUT should contain:
      """
      Success: Finished removing 15 old revisions.
      """

    When I run `wp revisions clean 5`
    Then STDOUT should contain:
      """
      Success: Finished removing 30 old revisions.
      """

    When I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      15
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
