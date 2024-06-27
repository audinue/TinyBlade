<?php

class TinyBladeIO
{
  function session($name)
  {
    return isset($_SESSION[$name]);
  }

  function exists($file)
  {
    return is_file($file);
  }

  function read($file)
  {
    return file_get_contents($file);
  }

  function write($file, $content)
  {
    file_put_contents($file, $content);
  }

  function modified($file)
  {
    return filemtime($file);
  }

  function include($context, $file, $scope)
  {
    (function ($__file, $__scope) {
      extract($__scope);
      include $__file;
    })->call($context, $file, $scope);
  }
}

class TinyBlade
{
  private $empty;
  private $page;

  function __construct(
    private $views = '.',
    private $cache = null,
    private $io = new TinyBladeIO()
  ) {
  }

  #region Compiler

  private function _php()
  {
    return '<?php';
  }

  private function _endphp()
  {
    return '?>';
  }

  private function _class($expression)
  {
    return '<?= $this->class(' . $expression . ') ?>';
  }

  private function _style($expression)
  {
    return '<?= $this->style(' . $expression . ') ?>';
  }

  private function _checked($expression)
  {
    return '<?= ' . $expression . ' ? \'checked\' : \'\' ?>';
  }

  private function _selected($expression)
  {
    return '<?= ' . $expression . ' ? \'selected\' : \'\' ?>';
  }

  private function _disabled($expression)
  {
    return '<?= ' . $expression . ' ? \'disabled\' : \'\' ?>';
  }

  private function _readonly($expression)
  {
    return '<?= ' . $expression . ' ? \'readonly\' : \'\' ?>';
  }

  private function _required($expression)
  {
    return '<?= ' . $expression . ' ? \'required\' : \'\' ?>';
  }

  private function _if($expression)
  {
    return '<?php if (' . $expression . '): ?>';
  }

  private function _elseif($expression)
  {
    return '<?php elseif (' . $expression . '): ?>';
  }

  private function _else()
  {
    return '<?php else: ?>';
  }

  private function _endif()
  {
    return '<?php endif ?>';
  }

  private function _unless($expression)
  {
    return '<?php if (!(' . $expression . ')): ?>';
  }

  private function _endunless()
  {
    return '<?php endif ?>';
  }

  private function _isset($expression)
  {
    return '<?php if (isset(' . $expression . ')): ?>';
  }

  private function _endisset()
  {
    return '<?php endif ?>';
  }

  private function _empty($expression)
  {
    return $expression == ''
      ? '<?php endforeach; if ($__empty' . $this->empty . '): ?>'
      : '<?php if (empty(' . $expression . ')): ?>';
  }

  private function _endempty()
  {
    return '<?php endif ?>';
  }

  private function _session($expression)
  {
    return '<?php if ($this->session(' . $expression . ')): ?>';
  }

  private function _endsession()
  {
    return '<?php endif ?>';
  }

  private function _switch($expression)
  {
    return '<?php switch (' . $expression . '): ?>';
  }

  private function _case($expression)
  {
    return '<?php case ' . $expression . ': ?>';
  }

  private function _default()
  {
    return '<?php default: ?>';
  }

  private function _endswitch()
  {
    return '<?php endswitch ?>';
  }

  private function _break($expression)
  {
    return $expression == ''
      ? '<?php break ?>'
      : '<?php if (' . $expression . ') break ?>';
  }

  private function _continue($expression)
  {
    return $expression == ''
      ? '<?php continue ?>'
      : '<?php if (' . $expression . ') continue ?>';
  }

  private function _while($expression)
  {
    return '<?php while (' . $expression . '): ?>';
  }

  private function _endwhile()
  {
    return '<?php endwhile ?>';
  }

  private function _for($expression)
  {
    return '<?php for (' . $expression . '): ?>';
  }

  private function _endfor()
  {
    return '<?php endfor ?>';
  }

  private function _foreach($expression)
  {
    return '<?php foreach (' . $expression . '): ?>';
  }

  private function _endforeach()
  {
    return '<?php endforeach ?>';
  }

  private function _forelse($expression)
  {
    $this->empty++;
    return '<?php $__empty' . $this->empty . ' = 1; foreach (' . $expression . '): $__empty' . $this->empty . ' = 0 ?>';
  }

  private function _endforelse()
  {
    $this->empty--;
    return '<?php endif ?>';
  }

  private function _include($expression)
  {
    return '<?php $this->include($__scope, ' . $expression . ') ?>';
  }

  private function _extends($expression)
  {
    return '<?php $this->extends($__scope, ' . $expression . ') ?>';
  }

  private function _yield($expression)
  {
    return '<?php $this->yield(' . $expression . ') ?>';
  }

  private function _stack($expression)
  {
    return '<?php $this->stack(' . $expression . ') ?>';
  }

  private function _section($expression)
  {
    return '<?php $this->section(' . $expression . ') ?>';
  }

  private function _endsection()
  {
    return '<?php $this->endsection() ?>';
  }

  private function _parent()
  {
    return '<?php $this->parent() ?>';
  }

  private function _push($expression)
  {
    return '<?php $this->push(' . $expression . ') ?>';
  }

  private function _endpush()
  {
    return '<?php $this->endpush() ?>';
  }

  private function _prepend($expression)
  {
    return '<?php $this->prepend(' . $expression . ') ?>';
  }

  private function _endprepend()
  {
    return '<?php $this->endprepend() ?>';
  }

  private function _show()
  {
    return '<?php $this->show() ?>';
  }

  private function replace($matches)
  {
    if ($matches[1] != '') {
      return $matches[1];
    }
    if ($matches[2] != '') {
      return '';
    }
    if ($matches[3] != '') {
      return '<?= htmlspecialchars(' . $matches[3] . ') ?>';
    }
    if ($matches[4] != '') {
      return '<?=' . $matches[4] . '?>';
    }
    return [$this, '_' . $matches[5]](trim($matches[6], '()'))
      . ($matches[5] == 'switch' ? '' : $matches[8]);
  }

  private function compile($string)
  {
    $this->empty = 0;
    return preg_replace_callback(
      '/@([@{])|\{\{--(.+?)--\}\}|\{\{(.+?)\}\}|\{!!(.+?)!!\}|@([a-z]+)(?:\s*(\(((?:[^()]+|(?6))*)\)))?(\s*)/s',
      [$this, 'replace'],
      $string
    );
  }

  #endregion

  #region Runtime

  private function session($name)
  {
    return $this->io->session($name);
  }

  private function class($array)
  {
    $classes = [];
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        $classes[] = $value;
      } else if ($value) {
        $classes[] = $key;
      }
    }
    echo 'class="' . htmlspecialchars(implode(' ', $classes)) . '"';
  }

  private function style($array)
  {
    $styles = [];
    foreach ($array as $key => $value) {
      if (is_numeric($key)) {
        $styles[] = $value;
      } else if ($value) {
        $styles[] = $key;
      }
    }
    echo 'style="' . htmlspecialchars(implode(';', $styles)) . '"';
  }

  private function section($name, $value = null)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $this->page->section = (object) [
      'previous' => $this->page->section,
      'parent' => isset($this->page->sections[$name])
        ? $this->page->sections[$name]
        : null,
      'name' => $name,
      'children' => [],
    ];
    ob_start();
    if ($value !== null) {
      echo $value;
      $this->endsection();
    }
  }

  private function endsection()
  {
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $section = $this->page->section;
    $this->page->section = $section->previous;
    $this->page->sections[$section->name] = $section;
  }

  private function parent()
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $parent = $this->page->section->parent;
    $this->page->section->children[] = function ($page) use ($parent) {
      return implode(
        '',
        array_map(
          function ($child) use ($page) {
            return $child($page);
          },
          $parent->children
        )
      );
    };
  }

  private function yield($name, $default = null)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $this->page->section->children[] = function ($page) use ($name, $default) {
      if (!isset($page->sections[$name])) {
        return $default;
      }
      return implode(
        '',
        array_map(
          function ($child) use ($page) {
            return $child($page);
          },
          $page->sections[$name]->children
        )
      );
    };
  }

  private function show()
  {
    $name = $this->page->section->name;
    $this->endsection();
    $this->yield($name);
  }

  private function push($name)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $this->page->section = (object) [
      'previous' => $this->page->section,
      'name' => $name,
      'children' => [],
    ];
    ob_start();
  }

  private function endpush()
  {
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $section = $this->page->section;
    $this->page->section = $section->previous;
    $this->page->operations[$section->name][] = function (&$stack) use ($section) {
      array_push($stack, $section);
    };
  }

  private function prepend($name)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $this->page->section = (object) [
      'previous' => $this->page->section,
      'name' => $name,
      'children' => [],
    ];
    ob_start();
  }

  private function endprepend()
  {
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $section = $this->page->section;
    $this->page->section = $section->previous;
    $this->page->operations[$section->name][] = function (&$stack) use ($section) {
      array_unshift($stack, $section);
    };
  }

  private function stack($name)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $this->page->section->children[] = function ($page) use ($name) {
      $stack = [];
      foreach ($page->operations[$name] ?? [] as $operation) {
        $operation($stack);
      }
      return implode(
        '',
        array_map(
          function ($section) use ($page) {
            return implode(
              '',
              array_map(
                function ($child) use ($page) {
                  return $child($page);
                },
                $section->children
              )
            );
          },
          $stack
        )
      );
    };
  }

  private function include($__scope, $__name, ...$extra)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $previous = $this->page;
    $this->page = (object) [
      'parent' => null,
      'sections' => [],
      'operations' => [],
      'section' => (object) [
        'children' => []
      ]
    ];
    ob_start();
    $file = $this->views . '/' . str_replace('.', '/', $__name) . '.blade.php';
    if ($this->cache) {
      $cache = $this->cache . '/' . str_replace('.', '/', $__name) . '.php';
      if (!$this->io->exists($cache) || $this->io->modified($cache) < $this->io->modified($file)) {
        $this->io->write($cache, $this->compile($this->io->read($file)));
      }
      $this->io->include($this, $cache, [...$__scope, ...$extra]);
    } else {
      $code = $this->compile($this->io->read($file));
      (function ($__code, $__scope) {
        extract($__scope);
        eval ('?>' . $__code);
      })($code, [...$__scope, ...$extra]);
    }
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $leaf = $this->page;
    $root = $leaf;
    while ($root->parent) {
      $root = $root->parent;
    }
    $content = implode(
      '',
      array_map(
        function ($child) use ($leaf) {
          return $child($leaf);
        },
        $root->section->children
      )
    );
    $previous->section->children[] = function () use ($content) {
      return $content;
    };
    $this->page = $previous;
  }

  private function extends($scope, $name)
  {
    $content = ob_get_contents();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    ob_clean();
    $previous = $this->page;
    $this->page = (object) [
      'parent' => null,
      'sections' => [],
      'operations' => [],
      'section' => (object) [
        'children' => []
      ]
    ];
    ob_start();
    $file = $this->views . '/' . str_replace('.', '/', $name) . '.blade.php';
    if ($this->cache) {
      $cache = $this->cache . '/' . str_replace('.', '/', $name) . '.php';
      if (!$this->io->exists($cache) || $this->io->modified($cache) < $this->io->modified($file)) {
        $this->io->write($cache, $this->compile($this->io->read($file)));
      }
      $this->io->include($this, $cache, $scope);
    } else {
      (function ($__code, $__scope) {
        extract($__scope);
        eval ('?>' . $__code);
      })($this->compile($this->io->read($file)), $scope);
    }
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $previous->parent = $this->page;
    $previous->sections = $this->page->sections;
    $previous->operations = $this->page->operations;
    $this->page = $previous;
  }

  #endregion

  #region API

  function renderString($__code, ...$__scope)
  {
    $this->page = (object) [
      'parent' => null,
      'sections' => [],
      'operations' => [],
      'section' => (object) [
        'children' => []
      ]
    ];
    ob_start();
    extract($__scope);
    eval ('?>' . $this->compile($__code));
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $leaf = $this->page;
    $root = $leaf;
    while ($root->parent) {
      $root = $root->parent;
    }
    return implode(
      '',
      array_map(
        function ($child) use ($leaf) {
          return $child($leaf);
        },
        $root->section->children
      )
    );
  }

  function renderFile($__name, ...$scope)
  {
    $this->page = (object) [
      'parent' => null,
      'sections' => [],
      'operations' => [],
      'section' => (object) [
        'children' => []
      ]
    ];
    ob_start();
    $file = $this->views . '/' . str_replace('.', '/', $__name) . '.blade.php';
    if ($this->cache) {
      $cache = $this->cache . '/' . str_replace('.', '/', $__name) . '.php';
      if (!$this->io->exists($cache) || $this->io->modified($cache) < $this->io->modified($file)) {
        $this->io->write($cache, $this->compile($this->io->read($file)));
      }
      $this->io->include($this, $cache, $scope);
    } else {
      $code = $this->compile($this->io->read($file));
      (function ($__code, $__scope) {
        extract($__scope);
        eval ('?>' . $__code);
      })($code, $scope);
    }
    $content = ob_get_clean();
    $this->page->section->children[] = function () use ($content) {
      return $content;
    };
    $leaf = $this->page;
    $root = $leaf;
    while ($root->parent) {
      $root = $root->parent;
    }
    return implode(
      '',
      array_map(
        function ($child) use ($leaf) {
          return $child($leaf);
        },
        $root->section->children
      )
    );
  }

  #endregion
}
