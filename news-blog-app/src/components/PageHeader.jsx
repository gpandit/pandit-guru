import { motion } from 'framer-motion';
import { StaggerText } from './Kinetic';

export default function PageHeader({ eyebrow, titleA, titleB, sub }) {
  return (
    <>
      <motion.div
        className="page-eyebrow"
        initial={{ opacity: 0, x: -16 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.5, delay: 0.1 }}
      >
        {eyebrow}
      </motion.div>
      <h1 className="page-title display">
        <StaggerText text={titleA} delay={0.2} />
        {titleB && <>{' '}<StaggerText text={titleB} delay={0.45} className="hi" /></>}
      </h1>
      <motion.p
        className="page-sub"
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6, delay: 0.65 }}
      >
        {sub}
      </motion.p>
    </>
  );
}
