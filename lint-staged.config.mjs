export default {
  "*.php": ["composer lint --", "composer phpstan --"],
  "*.{yml,yaml,md,json}": ["prettier --write"],
};
