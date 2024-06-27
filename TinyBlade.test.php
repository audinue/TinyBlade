<?php

$tests = [
  ['{{ $foo }}', ['foo' => '&bar'], '&amp;bar'],
  ['{!! $foo !!}', ['foo' => '&bar'], '&bar'],
  ['{{-- foo --}}', [], ''],
  ['@php echo 1 @endphp', [], '1'],
  ['@@', [], '@'],
  ['@@foo', [], '@foo'],
  ['@{', [], '{'],
  ['@class(["foo", "bar" => 0, "baz" => 1])', [], 'class="foo baz"'],
  ['@style(["foo", "bar" => 0, "baz" => 1])', [], 'style="foo;baz"'],
  ['@checked(true)', [], 'checked'],
  ['@checked(false)', [], ''],
  ['@selected(true)', [], 'selected'],
  ['@selected(false)', [], ''],
  ['@disabled(true)', [], 'disabled'],
  ['@disabled(false)', [], ''],
  ['@readonly(true)', [], 'readonly'],
  ['@readonly(false)', [], ''],
  ['@required(true)', [], 'required'],
  ['@required(false)', [], ''],
  ['@if(true)1@elseif(true)2@else3@endif', [], '1'],
  ['@if(false)1@elseif(true)2@else3@endif', [], '2'],
  ['@if(false)1@elseif(false)2@else3@endif', [], '3'],
  ['@unless(true)1@endunless', [], ''],
  ['@unless(false)1@endunless', [], '1'],
  ['@empty(0)1@endempty', [], '1'],
  ['@empty(1)1@endempty', [], ''],
  ['@session("foo")1@endsession', [], '1'],
  ['@session("bar")1@endsession', [], ''],
  ['@switch(1)' . PHP_EOL . '  @case(1) 1@break@case(2) 2@break@default 3@endswitch', [], ' 1'],
  ['@switch(2)@case(1) 1@break@case(2) 2@break@default 3@endswitch', [], ' 2'],
  ['@switch(3)@case(1) 1@break@case(2) 2@break@default 3@endswitch', [], ' 3'],
  ['@php $i = 1 @endphp@while($i++)1@break($i>3)@endwhile', [], ' 111'],
  ['@php $i = 1 @endphp@while($i++<3)1@continue($i>2)2@endwhile', [], ' 121'],
  ['@for($i = 0; $i < 3; $i++)1@endfor', [], ' 111'],
  ['@foreach(range(1, 3) as $i)1@endforeach', [], ' 111'],
  ['@forelse([] as $i)1@empty2@endforelse', [], '2'],
  ['@forelse([1] as $i)1@empty2@endforelse', [], '1'],
  ['@extends("foo")bar', [], 'foo'],
  ['@section("foo")foo@endsection', [], ''],
  ['@section("foo")foo@endsection@yield("foo")', [], 'foo'],
  ['@yield("foo", "bar")', [], 'bar'],
  ['@section("foo")foo@show', [], 'foo'],
  ['@stack("foo")@push("foo")foo@endpush@push("foo")bar@endpush', [], 'foobar'],
  ['@stack("foo")@prepend("foo")foo@endprepend@prepend("foo")bar@endprepend', [], 'barfoo'],
  ['@include("foo")', [], 'foo'],
  ['@extends("bar")', [], 'foobar'],
  ['@extends("bar")@section("foo")bar@endsection', [], 'bar'],
  ['@extends("bar")@section("foo")@parent baz@endsection', [], 'foobar baz'],
  ['@extends("bar")@section("foo")bar@parent@endsection', [], 'barfoobar'],
  ['@extends("bar")@section("bar")baz@endsection', [], 'foobaz'],
  ['@extends("bar")@push("foo")foo@endpush', [], 'foobarfoo'],
  ['@extends("bar")@prepend("foo")foo@endprepend', [], 'foobarfoo'],
];

require __DIR__ . '/TinyBlade.php';

class FakeIO extends TinyBladeIO
{
  function __construct(public $files = [], public $sessions = [])
  {
  }

  function session($name)
  {
    return isset($this->sessions[$name]);
  }

  function exists($file)
  {
    return isset($this->files[$file]);
  }

  function read($file)
  {
    return $this->files[$file]->content;
  }

  function write($file, $content)
  {
    $this->files[$file] = (object) [
      'content' => $content,
      'modified' => time()
    ];
  }

  function modified($file)
  {
    return $this->files[$file]->modified ?? 0;
  }

  function include($context, $file, $scope)
  {
    (function ($__code, $__scope) {
      extract($__scope);
      eval ('?>' . $__code);
    })->call($context, $this->read($file), $scope);
  }
}

$tb = new TinyBlade(
  io: new FakeIO(
    files: [
      './foo.blade.php' => (object) [
        'content' => 'foo'
      ],
      './bar.blade.php' => (object) [
        'content' => '@section("foo")foo@section("bar")bar@show@show@stack("foo")'
      ],
    ],
    sessions: [
      'foo' => 1
    ]
  )
);

foreach ($tests as [$input, $scope, $expected]) {
  $actual = $tb->renderString($input, ...$scope);
  if ($actual != $expected) {
    var_dump($input, $scope, $expected, $actual);
    die;
  }
}

echo 'AWESOME';
