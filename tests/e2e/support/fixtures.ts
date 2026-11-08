import { test as base, expect, type Page } from '@playwright/test';
import { withOptions, type Options } from './options';

export const test = base.extend<{ options: Options }>( {
	options: async ( {}, use ) => withOptions( use ),
} );

export { expect };
export type { Page };
