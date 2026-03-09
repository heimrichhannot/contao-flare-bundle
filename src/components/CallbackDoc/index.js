import React from 'react';
import clsx from 'clsx';
import CodeBlock from '@theme/CodeBlock';
import styles from './styles.module.css';

export default function CallbackDoc({ attribute, target, description, arguments: args, returnType, returnDescription, code }) {
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
            <h4 className={styles.sectionTitle}>Available Arguments</h4>
            <div className={styles.argumentList}>
              <div className={styles.argumentListHeader}>
                <span>Type or class</span>
                <span>Notes</span>
              </div>
              <div className={styles.argumentItems}>
                {args.map((arg, idx) => (
                  <article key={idx} className={styles.argumentItem}>
                    <div className={styles.argumentMeta}>
                      <div className={styles.argumentSignature}>
                        <code>{arg.name ? `${arg.type} ${arg.name}` : arg.type}</code>
                      </div>
                      {arg.positional && (
                        <span className={styles.positionalBadge}>
                          <span className={styles.positionalBadgeDot} aria-hidden="true" />
                          Positional
                        </span>
                      )}
                    </div>
                    <p className={styles.argumentDescription}>{arg.description}</p>
                  </article>
                ))}
              </div>
            </div>
          </>
        )}

        {returnType && (
          <>
            <h4 className={styles.sectionTitle}>Return Type</h4>
            <div className={styles.returnBlock}>
              <div className={styles.returnRow}>
                <code className={styles.returnCode}>{returnType}</code>
                {returnDescription && (
                  <p className={styles.returnDescription}>{returnDescription}</p>
                )}
              </div>
            </div>
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
