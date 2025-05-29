import "./block-category";

if (typeof memberpressBlocks !== 'undefined' && memberpressBlocks.block_protection) {
  require('./block-protection');
}
