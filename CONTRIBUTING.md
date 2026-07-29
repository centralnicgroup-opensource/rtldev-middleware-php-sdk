# Contributing

When contributing to this repository, please first discuss the change you wish to make via issue,
email, or any other method with the owners of this repository before making a change.

Please note we have a code of conduct, please follow it in all your interactions with the project.

## Development

Coding standards, testing conventions and the architecture are documented in [CLAUDE.md](CLAUDE.md) and [docs/agents/](docs/agents/). Run `composer lint` and `composer test` before opening a pull request.

### Where a change gets documented

[CLAUDE.md](CLAUDE.md) is a working brief, read in full on every task, so its size is a running cost — it stays at one imperative line per rule. The long-form material has fixed homes, and a change should land in them rather than in a new CLAUDE.md paragraph:

- A **`BREAKING CHANGE:`** commit must extend [MIGRATION.md](MIGRATION.md) (the consumer upgrade path — a `→ vX.0.0` section plus its compatibility-table row, linked from the commit footer) and [docs/agents/architecture.md](docs/agents/architecture.md) (the decision record: what was rejected, the failure mode prevented, the guard test that locks it). Neither is a reason to touch CLAUDE.md.
- Rationale, alternatives considered and ticket history go to `docs/agents/*.md` — architecture decisions to `architecture.md`, test-harness detail to `testing.md`, version/packaging/tooling policy to `project-policies.md`, CI and release to `ci-release.md`.
- Edit CLAUDE.md only when a rule needed on _every_ task actually changes, and keep the pointer there rather than a summary: a summary next to its source is a second copy that drifts.

### Guard tests

Settled structural decisions in this SDK are each locked by a **guard test** — the `tests/*SeamTest.php` files, plus a few named guards outside that glob. They exist because undoing one of those decisions is _behaviour-preserving on the day it lands_: base-class defaults would return exactly what the brand returns today, an inlined `new ResponseParser()` behaves identically to the injected one. No behavioural test can see that arrive, so the guards assert structure through reflection instead.

Two rules follow, and they cut in opposite directions:

- **Never delete or weaken a guard test to make a change pass.** A failing guard means you are undoing a decision, not fixing a test. Read the test's class docblock and the matching entry in [docs/agents/architecture.md](docs/agents/architecture.md) first; if the decision should genuinely be reopened, that is a discussion and a ticket, not a diff.
- **A new guard test must carry its own rationale in its class docblock**, because that docblock is the only thing a future contributor is guaranteed to read before touching it. State four things: the directive, the failure mode it prevents, why the guard has to be structural rather than behavioural, and the one condition that would justify revisiting the decision. [tests/ResponsePaginationSeamTest.php](tests/ResponsePaginationSeamTest.php) is the reference example.

Then prove the guard is not vacuous: apply the mutation it is supposed to refuse, confirm the guard fails, and confirm the rest of the suite stays green — that green suite is the whole argument for the test existing. Note that `.github/phpunit.xml` sets `stopOnDefect="true"`, so a plain `composer test` halts at the first failure; set the guard aside temporarily to observe the "nothing else fails" half.

## Code of Conduct

### Our Pledge

In the interest of fostering an open and welcoming environment, we as
contributors and maintainers pledge to making participation in our project and
our community a harassment-free experience for everyone, regardless of age, body
size, disability, ethnicity, gender identity and expression, level of experience,
nationality, personal appearance, race, religion, or sexual identity and
orientation.

### Our Standards

Examples of behavior that contributes to creating a positive environment
include:

- Using welcoming and inclusive language
- Being respectful of differing viewpoints and experiences
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards other community members

Examples of unacceptable behavior by participants include:

- The use of sexualized language or imagery and unwelcome sexual attention or
  advances
- Trolling, insulting/derogatory comments, and personal or political attacks
- Public or private harassment
- Publishing others' private information, such as a physical or electronic
  address, without explicit permission
- Other conduct which could reasonably be considered inappropriate in a
  professional setting

### Our Responsibilities

Project maintainers are responsible for clarifying the standards of acceptable
behavior and are expected to take appropriate and fair corrective action in
response to any instances of unacceptable behavior.

Project maintainers have the right and responsibility to remove, edit, or
reject comments, commits, code, wiki edits, issues, and other contributions
that are not aligned to this Code of Conduct, or to ban temporarily or
permanently any contributor for other behaviors that they deem inappropriate,
threatening, offensive, or harmful.

### Scope

This Code of Conduct applies both within project spaces and in public spaces
when an individual is representing the project or its community. Examples of
representing a project or community include using an official project e-mail
address, posting via an official social media account, or acting as an appointed
representative at an online or offline event. Representation of a project may be
further defined and clarified by project maintainers.

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be
reported by contacting the project team. All complaints will be reviewed and
investigated and will result in a response that is deemed necessary and appropriate
to the circumstances. The project team is obligated to maintain confidentiality
with regard to the reporter of an incident. Further details of specific enforcement
policies may be posted separately.

Project maintainers who do not follow or enforce the Code of Conduct in good
faith may face temporary or permanent repercussions as determined by other
members of the project's leadership.

### Attribution

This Code of Conduct is adapted from the [Contributor Covenant][homepage], version 1.4,
available at [http://contributor-covenant.org/version/1/4][version]

[homepage]: http://contributor-covenant.org
[version]: http://contributor-covenant.org/version/1/4/
