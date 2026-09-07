<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateVisualParityTest extends TestCase
{
    /**
     * @param string[] $expectedFragments
     */
    #[DataProvider('templateProvider')]
    public function testKeepsCriticalUpstreamLayoutClasses(string $template, array $expectedFragments): void
    {
        $modulePath = dirname(__DIR__, 3);
        $contents = file_get_contents($modulePath . '/view/frontend/templates/components/' . $template);

        self::assertIsString($contents);
        foreach ($expectedFragments as $fragment) {
            self::assertStringContainsString($fragment, $contents);
        }
    }

    /**
     * @return array<string, array{string, string[]}>
     */
    public static function templateProvider(): array
    {
        return [
            'banner A' => ['banner/a.phtml', [
                'relative grid *:col-start-1 *:row-start-1',
                'before:absolute before:inset-0 before:bg-[var(--banner-bg)] before:opacity-40',
                "'card m-6 md:p-8 rounded-lg' : 'p-6 md:p-12'",
            ]],
            'banner B' => ['banner/b.phtml', [
                'relative grid grid-cols-1 md:grid-cols-2',
                "'isolate p-6 md:p-12'",
                'place-self: center stretch',
            ]],
            'banner C' => ['banner/c.phtml', [
                "'relative'",
                'class="isolate p-6 md:p-12"',
                'my-2 text-lg font-medium lg:text-2xl',
            ]],
            'accordion A' => ['accordion/a.phtml', [
                "'py-3 px-6 bg-gray-100'",
                'class="group details-animate"',
                'class="flow-root pb-3"',
            ]],
            'generic content A' => ['generic-content/a.phtml', [
                'max-w-full prose prose-slate prose-li:my-0 prose-p:my-8 prose-lead:mt-0',
                'class="mb-12 space-y-8"',
                'class="md:columns-2 md:gap-x-8"',
            ]],
            'card A' => ['card/a.phtml', [
                'relative card card-interactive flex flex-col overflow-clip p-0',
                'media="(min-width: 1024px)"',
                'class="card-content p-6 prose"',
                'class="mt-8 mb-0"',
                'before:absolute before:inset-0',
            ]],
            'card B' => ['card/b.phtml', [
                'relative card card-interactive flex items-start gap-6 overflow-clip',
                'width="150"',
                'height="150"',
                'max-w-1/2 sm:shrink-0',
                'class="prose grow',
                'class="mt-8 mb-0"',
                'before:absolute before:inset-0',
            ]],
            'categories A' => ['categories/a.phtml', [
                'class="my-10"',
                'flex-wrap flex justify-between items-baseline mb-2',
                'gap-4 snap flex overflow-x-auto py-6 md:snap-none md:overflow-x-visible md:gap-4 md:grid md:grid-cols-2',
                'h-[300px] sm:h-[400px] md:h-[500px] lg:h-[600px]',
                'md:first:col-span-full shrink-0 snap-start',
                'absolute w-full h-full object-cover md:group-hover:scale-105 transition duration-300',
                'text-white drop-shadow-lg bg-gradient-to-b from-transparent to-gray-800',
            ]],
            'categories B' => ['categories/b.phtml', [
                'class="my-10"',
                'flex-wrap flex justify-between items-baseline mb-2',
                'gap-4 snap flex overflow-x-auto py-6 md:snap-none md:overflow-x-visible md:gap-4 md:grid md:grid-cols-2',
                'h-72 md:h-80 md:first:h-40',
                'md:hover:shadow-2xl md:hover:scale-[1.02] transition duration-300',
                "'wiggle' => 'bg-wiggle'",
                "'bank_note' => 'bg-bank-note'",
                "'pink' => 'bg-pink-500'",
                "'blue' => 'bg-blue-800'",
                'md:first:col-span-full shrink-0 snap-start',
                'drop-shadow-lg text-white text-xl leading-10 font-bold uppercase',
            ]],
            'embed A' => ['embed/a.phtml', [
                'group grid *:col-start-1 *:row-start-1 aspect-video rounded-lg',
                'bg-[var(--play-btn-bg)] text-[var(--play-btn-fg)]',
                'relative flex flex-col justify-center items-center rounded-lg bg-no-repeat bg-cover',
                'rounded-full p-4 shadow-lg bg-[var(--play-btn-bg)] text-[var(--play-btn-fg)]',
                'group-hover:scale-125 transition-transform',
                'referrerpolicy="strict-origin-when-cross-origin"',
                'class="max-w-full w-full h-full rounded-lg overflow-clip"',
            ]],
            'generic content B' => ['generic-content/b.phtml', [
                'lg:grid lg:grid-cols-2 lg:gap-x-2 bg-zinc-100',
                'col-start-1 row-start-1 lg:w-full lg:h-full lg:object-cover',
                'lg:hidden col-start-1 row-start-1 self-end pt-16 pb-8 px-6',
                'bg-gradient-to-t from-slate-800/75 text-white',
                'inline-block mb-2 px-2 py-1 rounded bg-blue-800 text-blue-100',
                'p-6 max-w-full prose prose-slate prose-p:text-slate-500 prose-lead:text-slate-600',
                'class="hidden lg:block"',
                'class="mb-12 space-y-2"',
                'class="lead text-lg font-medium"',
                'before:content-[open-quote]',
            ]],
            'modal A' => ['modal/a.phtml', [
                'class="btn btn-primary"',
                'x-htmldialog.noscroll="close"',
                'class="rounded-xl p-0 shadow-xl lg:max-w-3xl"',
                'p-7 flex flex-col items-center gap-4 text-center lg:flex-row lg:items-start lg:text-left lg:gap-6',
                "'inline-block shrink-0 text-yellow-400'",
                'class="text-slate-600 text-pretty"',
                'bg-gray-100 p-7 flex flex-col gap-4 lg:flex-row lg:justify-end',
                'btn border-slate-300 text-slate-600 flex-1 lg:flex-initial',
                'btn btn-primary flex-1 lg:flex-initial',
            ]],
            'product highlights C' => ['product-highlights/c.phtml', [
                "'md:flex-row-reverse' : 'md:flex-row'",
                'md:items-start gap-4 my-6',
                'width="640"',
                'height="640"',
                'class="shrink-0 m-0 aspect-square object-cover md:w-48"',
                'class="space-y-2"',
                'class="mt-0 mb-2"',
            ]],
            'shortcuts A' => ['shortcuts/a.phtml', [
                'class="bg-blue-900 p-7"',
                'class="container mx-auto"',
                'class="grid gap-16 sm:grid-cols-3"',
                'flex flex-col items-center gap-4 xl:flex-row xl:gap-8',
                'h-12 w-12 xl:h-16 xl:w-16',
                'class="text-center xl:text-left"',
                'class="text-xl leading-7 font-semibold text-white"',
                'class="text-gray-400 leading-6 mt-1 hidden lg:block"',
            ]],
            'slider A' => ['slider/a.phtml', [
                'class="relative my-20"',
                'x-snap-slider.auto-pager',
                'hidden sm:flex gap-4 justify-center items-center mt-7 absolute w-full bottom-8',
                'aria-[current=true]:ring-white aria-[current=true]:bg-blue-600',
                'z-10 flex gap-2 justify-between absolute bottom-4 sm:static',
                'sm:z-10 sm:absolute sm:top-1/2 sm:-translate-y-1/2 sm:left-4',
                'sm:z-10 sm:absolute sm:top-1/2 sm:-translate-y-1/2 sm:right-4',
                'snap relative grid grid-flow-col auto-cols-[100%] overflow-x-auto overscroll-x-contain',
                'shrink-0 grid *:row-start-1 *:col-start-1 w-full',
                'w-full h-[460px] md:h-[580px] lg:h-[800px] object-cover',
                'bg-gradient-to-t from-gray-800/50 to-transparent text-white text-center uppercase',
            ]],
            'slider B' => ['slider/b.phtml', [
                'class="relative my-20"',
                '--size: clamp(10rem, 1rem + 40vmin, 30rem)',
                'class="mb-4 sm:flex sm:justify-between sm:items-baseline"',
                'class="text-3xl leading-9 font-bold text-gray-800"',
                'text-blue-700 text-lg leading-7 font-semibold',
                'flex gap-[var(--gap)] select-none overflow-hidden mask-overflow border-y border-gray-300 py-10 md:py-7',
                'shrink-0 flex gap-[var(--gap)] items-center justify-around min-w-full',
                'motion-reduce:[animation-play-state:paused]',
                '<div aria-hidden="true"',
                '@keyframes marqueeScroll',
            ]],
            'testimonial A' => ['testimonial/a.phtml', [
                'relative flex flex-col text-center px-14 gap-8',
                'text-blue-100 text-9xl font-bold absolute left-0 -top-8',
                'flex-1 text-gray-800 text-2xl leading-8 font-medium',
                'flex justify-center gap-4 items-center',
                'rounded-full overflow-hidden w-28 h-28',
                'width="120"',
                'height="129"',
                'text-gray-600 text-lg leading-7 font-bold',
                'text-blue-300 leading-6 mt-1',
            ]],
            'testimonial B' => ['testimonial/b.phtml', [
                'mt-24 p-6 bg-[#E2E8F0] rounded-xl lg:flex lg:gap-10',
                'flex justify-center -mt-24 lg:mt-0',
                'rounded-full overflow-hidden w-36 h-36 lg:w-56 lg:h-56',
                'width="240"',
                'height="258"',
                'mt-6 text-center lg:text-left ',
                'text-[#CBD5E1] text-9xl font-bold absolute left-0 -top-8 lg:-ml-6',
                'relative text-gray-800 text-2xl font-medium max-w-4xl lg:pl- xl:text-3xl',
                'text-blue-600 text-lg leading-7 font-bold mt-4',
                'text-[#94A3B8] leading-6',
            ]],
            'USP A' => ['usp/a.phtml', [
                'class="p-6 leading-6 text-gray-500"',
                'class="flex flex-col items-center gap-2"',
                'uppercase bg-blue-100 rounded px-2 py-1 text-blue-600 text-lg leading-7 font-semibold md:text-xl',
                'text-4xl leading-10 font-bold text-gray-800 md:text-5xl',
                'class="mt-8 grid gap-6 md:grid-cols-2"',
                'class="flex gap-4"',
                'w-16 h-16 bg-blue-200 text-blue-800 rounded-full flex justify-center items-center',
                "('w-12 h-12', 48, 48",
                'text-lg text-gray-800 font-medium leading-7 mb-2',
            ]],
            'USP B' => ['usp/b.phtml', [
                'class="p-6 leading-6 text-gray-600"',
                'class="flex flex-col items-center gap-2"',
                'uppercase bg-blue-100 rounded px-2 py-1 text-blue-600 text-lg leading-7 font-semibold md:text-xl',
                'text-4xl leading-10 font-bold text-gray-800 md:text-5xl',
                'class="mt-8 grid gap-4 md:grid-cols-2"',
                'flex flex-col items-center gap-4 bg-blue-100 rounded-xl py-6 px-12',
                'w-16 h-16 bg-blue-600 text-white rounded-full flex justify-center items-center',
                "('w-12 h-12', 48, 48",
                'class="text-center"',
                'text-xl text-gray-800 font-medium leading-7 mb-2',
            ]],
            'USP C' => ['usp/c.phtml', [
                'class="p-6 leading-6 text-gray-600"',
                'class="grid gap-4 lg:grid-cols-3"',
                'flex items-center gap-6 bg-blue-100 rounded-xl p-6 lg:px-12 lg:flex-col lg:gap-4',
                'w-16 h-16 bg-blue-600 text-white rounded-full flex justify-center items-center',
                "('w-12 h-12', 48, 48",
                'class="lg:text-center"',
                'text-xl text-gray-800 font-medium leading-7 mb-2',
            ]],
        ];
    }
}
