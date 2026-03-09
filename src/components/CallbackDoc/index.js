import React from 'react';
import clsx from 'clsx';
import CodeBlock from '@theme/CodeBlock';
import styles from './styles.module.css';

export default function CallbackDoc({ attribute, target, description, arguments: args, code }) {
  const isList = attribute === 'List';

  return (
    <details className={styles.callbackContainer}>
      <summary className={styles.summary}>
        <span className={clsx(styles.attributeBadge, isList && styles.listBadge)}>
          {attribute}
        </span>
        <code className={styles.targetString}>{target}</code>
        <span className={styles.shortDescription}>{description}</span>
      </summary>
      
      <div className={styles.detailsContent}>
        {args && args.length > 0 && (
          <>
            <h4>Available Arguments</h4>
            <table className={styles.argumentTable}>
              <thead>
                <tr>
                  <th>Type / Name</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                {args.map((arg, idx) => (
                  <tr key={idx}>
                    <td>
                      <code>{arg.type}</code> {arg.name && <code>{arg.name}</code>}
                      {arg.positional && <span className={styles.positionalBadge}>Positional</span>}
                    </td>
                    <td>{arg.description}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
        
        <h4>Example Implementation</h4>
        <CodeBlock language="php">
          {code}
        </CodeBlock>
      </div>
    </details>
  );
}
