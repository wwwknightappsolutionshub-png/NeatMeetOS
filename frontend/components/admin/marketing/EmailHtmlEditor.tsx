'use client';

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useRef,
  type ReactNode,
} from 'react';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';

export type EmailHtmlEditorHandle = {
  insertText: (text: string) => void;
  focus: () => void;
};

type Props = {
  /** Initial HTML for this mount. Remount the component (React `key`) to load different content. */
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
};

function isEditorReady(editor: Editor | null): editor is Editor {
  return Boolean(editor && !editor.isDestroyed && editor.view);
}

function safeGetHtml(editor: Editor): string {
  if (!isEditorReady(editor)) return '';
  try {
    return editor.isEmpty ? '' : editor.getHTML();
  } catch {
    return '';
  }
}

function ToolbarButton({
  active,
  disabled,
  onClick,
  children,
  title,
}: {
  active?: boolean;
  disabled?: boolean;
  onClick: () => void;
  children: ReactNode;
  title: string;
}) {
  return (
    <button
      type="button"
      title={title}
      disabled={disabled}
      onClick={onClick}
      className={`rounded px-2 py-1 text-xs font-medium transition ${
        active ? 'bg-zinc-800 text-white' : 'bg-white text-zinc-700 hover:bg-zinc-100'
      } disabled:opacity-40`}
    >
      {children}
    </button>
  );
}

function Toolbar({ editor }: { editor: Editor | null }) {
  if (!isEditorReady(editor)) return null;

  return (
    <div className="flex flex-wrap gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5">
      <ToolbarButton
        title="Bold"
        active={editor.isActive('bold')}
        onClick={() => editor.chain().focus().toggleBold().run()}
      >
        Bold
      </ToolbarButton>
      <ToolbarButton
        title="Italic"
        active={editor.isActive('italic')}
        onClick={() => editor.chain().focus().toggleItalic().run()}
      >
        Italic
      </ToolbarButton>
      <ToolbarButton
        title="Underline"
        active={editor.isActive('underline')}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
      >
        Underline
      </ToolbarButton>
      <ToolbarButton
        title="Heading"
        active={editor.isActive('heading', { level: 2 })}
        onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
      >
        H2
      </ToolbarButton>
      <ToolbarButton
        title="Bullet list"
        active={editor.isActive('bulletList')}
        onClick={() => editor.chain().focus().toggleBulletList().run()}
      >
        List
      </ToolbarButton>
      <ToolbarButton
        title="Link"
        active={editor.isActive('link')}
        onClick={() => {
          const previous = editor.getAttributes('link').href as string | undefined;
          const url = window.prompt('Link URL', previous ?? 'https://');
          if (url === null) return;
          if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
          }
          editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        }}
      >
        Link
      </ToolbarButton>
      <ToolbarButton title="Undo" onClick={() => editor.chain().focus().undo().run()}>
        Undo
      </ToolbarButton>
      <ToolbarButton title="Redo" onClick={() => editor.chain().focus().redo().run()}>
        Redo
      </ToolbarButton>
    </div>
  );
}

export const EmailHtmlEditor = forwardRef<EmailHtmlEditorHandle, Props>(function EmailHtmlEditor(
  { value, onChange, placeholder = 'Write your email…' },
  ref,
) {
  const onChangeRef = useRef(onChange);
  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
      }),
      Underline,
      Link.configure({
        openOnClick: false,
        HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
      }),
      Placeholder.configure({ placeholder }),
    ],
    content: value || '',
    editorProps: {
      attributes: {
        class:
          'min-h-40 px-3 py-2 text-sm text-zinc-800 outline-none prose prose-sm max-w-none ' +
          '[&_a]:text-[var(--color-brand,#2f5a45)] [&_a]:underline ' +
          '[&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5',
      },
    },
    onUpdate: ({ editor: ed }) => {
      if (!isEditorReady(ed)) return;
      onChangeRef.current(safeGetHtml(ed));
    },
  });

  useImperativeHandle(
    ref,
    () => ({
      insertText(text: string) {
        if (!isEditorReady(editor)) return;
        editor.chain().focus().insertContent(text).run();
      },
      focus() {
        if (!isEditorReady(editor)) return;
        editor.chain().focus().run();
      },
    }),
    [editor],
  );

  return (
    <div className="overflow-hidden rounded-md border border-zinc-300 bg-white focus-within:border-zinc-500">
      <Toolbar editor={editor} />
      <EditorContent editor={editor} />
    </div>
  );
});
