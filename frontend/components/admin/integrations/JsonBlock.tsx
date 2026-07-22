interface JsonBlockProps {
  value: unknown;
  title?: string;
}

export function JsonBlock({ value, title }: JsonBlockProps) {
  const text = JSON.stringify(value ?? {}, null, 2);

  return (
    <div>
      {title ? <p className="mb-1 text-sm font-medium text-zinc-700">{title}</p> : null}
      <pre className="max-h-96 overflow-auto rounded-md border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-800">
        {text}
      </pre>
    </div>
  );
}
