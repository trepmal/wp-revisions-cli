Feature: Revisions

  Scenario: List revisions
    Given a WP install

    When I run `wp revisions list --format=json`
    Then STDOUT should contain:
      """
      []
      """

    When I run `wp revisions generate 1`
    And I run `wp revisions list`
    Then STDOUT should be a table containing rows:
      | ID | post_title     | post_parent |
      | 6  | Hello world!   | 1           |
      | 5  | Sample Page    | 2           |
      | 4  | Privacy Policy | 3           |

    When I run `wp revisions generate 1`
    And I run `wp revisions list --fields=ID,post_title,post_type,post_status`
    Then STDOUT should be a table containing rows:
      | ID | post_title     | post_type | post_status |
      | 6  | Hello world!   | revision  | inherit     |
      | 5  | Sample Page    | revision  | inherit     |
      | 4  | Privacy Policy | revision  | inherit     |

    When I run `wp revisions generate 2`
    And I run `wp revisions list --post_type=post`
    Then STDOUT should be a table containing rows:
      | ID | post_title   | post_parent |
      | 6  | Hello world! | 1           |
      | 9  | Hello world! | 1           |
      | 14 | Hello world! | 1           |
      | 15 | Hello world! | 1           |

    When I run `wp revisions generate 6`
    And I run `wp revisions list --post_type=page --format=count`
    Then STDOUT should contain:
      """
      20
      """
