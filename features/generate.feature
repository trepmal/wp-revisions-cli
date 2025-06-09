Feature: Revisions

  Scenario: Generate revisions
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

    When I run `wp revisions generate 7 --post_id=1`
    And I run `wp revisions list --format=count`
    Then STDOUT should contain:
      """
      62
      """

    When I run `wp revisions generate 1 --oldest_date=1993-05-20`
    And I run `wp revisions list --fields=ID,post_title,post_date --post_id=1`
    Then STDOUT should contain:
      """
      1993-05-20 00:00:00
      """
